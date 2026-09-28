<?php

namespace App\Services;

use App\Models\CoinTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliation for invalid coin rewards (compensating reversals).
 *
 * Detection is deliberately independent of the prevention layer so it can
 * catch credits that were made before the ownership rule existed. It reads
 * authoritative data only:
 *
 *   ad_reward_events.user_id  = the actor who triggered the reward event
 *   reports.user_id           = the owner of the associated report
 *
 * and treats an event as invalid when actor == owner with coins_credited > 0.
 * It then matches the event to the credited transaction (same user, report,
 * type, amount, time window). Only definitively matched, un-reversed credits
 * are reversed — anything ambiguous is reported and left untouched.
 *
 * No endpoint exposes this; it runs only as an internal scheduled command.
 */
class CoinReconciliationService
{
    public function __construct(private readonly FinancialLedgerService $ledger)
    {
    }

    /**
     * Find self-reward credit transactions that have not been reversed yet.
     *
     * @return array{
     *     scanned: int,
     *     reversed: array<int, array>,
     *     would_reverse: array<int, array>,
     *     already_reversed: array<int, array>,
     *     deferred: array<int, array>,
     *     unmatched: array<int, array>,
     *     ambiguous: array<int, array>,
     *     errors: array<int, array>
     * }
     */
    public function reconcileSelfRewards(int $limit = 200, int $withinDays = 30, bool $dryRun = false): array
    {
        $report = [
            'scanned' => 0,
            'reversed' => [],
            'would_reverse' => [],
            'already_reversed' => [],
            'deferred' => [],
            'unmatched' => [],
            'ambiguous' => [],
            'errors' => [],
        ];

        $events = DB::table('ad_reward_events as re')
            ->join('reports as r', 'r.id', '=', 're.report_id')
            ->whereColumn('re.user_id', 'r.user_id')
            ->where('re.coins_credited', '>', 0)
            ->where('re.created_at', '>=', now()->subDays($withinDays))
            ->orderByDesc('re.id')
            ->limit($limit)
            ->get([
                're.id as event_id',
                're.user_id',
                're.report_id',
                're.event_type',
                're.coins_credited',
                're.created_at',
            ]);

        foreach ($events as $event) {
            $report['scanned']++;

            $expectedType = $event->event_type === 'click' ? 'click_earning' : 'impression_earning';
            $eventTime = Carbon::parse($event->created_at);

            $matches = CoinTransaction::where('user_id', $event->user_id)
                ->where('report_id', $event->report_id)
                ->where('type', $expectedType)
                ->where('amount', $event->coins_credited)
                ->whereBetween('created_at', [
                    $eventTime->copy()->subMinutes(5),
                    $eventTime->copy()->addMinutes(5),
                ])
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                $report['unmatched'][] = [
                    'event_id' => $event->event_id,
                    'user_id' => $event->user_id,
                    'report_id' => $event->report_id,
                ];
                continue;
            }

            // The link lives on the reversal row (reverses_transaction_id),
            // not on the original credit.
            $matches->load('reversedBy');
            $unreversed = $matches->filter(fn (CoinTransaction $t) => $t->reversedBy === null);

            if ($unreversed->isEmpty()) {
                $report['already_reversed'][] = [
                    'event_id' => $event->event_id,
                    'transaction_ids' => $matches->pluck('id')->all(),
                ];
                continue;
            }

            if ($unreversed->count() > 1) {
                $report['ambiguous'][] = [
                    'event_id' => $event->event_id,
                    'transaction_ids' => $unreversed->pluck('id')->all(),
                ];
                continue;
            }

            $transaction = $unreversed->first();

            if ($dryRun) {
                $report['would_reverse'][] = [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'amount' => (float) $transaction->amount,
                ];
                continue;
            }

            try {
                $reversal = $this->ledger->reverseCoinTransaction(
                    originalTransactionId: $transaction->id,
                    reason: 'self_reward_reversal',
                    metadata: [
                        'original_reward_event_id' => $event->event_id,
                        'automatic' => true,
                        'detection' => 'self_reward_reconciliation',
                    ],
                );

                $report['reversed'][] = [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                    'reversal_id' => $reversal->id,
                    'user_id' => $transaction->user_id,
                    'amount' => (float) $transaction->amount,
                ];
            } catch (\RuntimeException $e) {
                $report['deferred'][] = [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ];
                Log::warning('Coin reversal deferred (retry next run): ' . $e->getMessage(), [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Coin reversal failed', [
                    'event_id' => $event->event_id,
                    'transaction_id' => $transaction->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $report;
    }
}
