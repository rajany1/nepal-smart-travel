<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Booking;
use App\Models\TravelPartner;
use Illuminate\Support\Facades\Log;

class BookingAssistantHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto';

        if ($action === 'assess') {
            return $this->assess($task);
        }

        if (in_array($action, ['auto', 'auto-work'])) {
            return $this->handleAutoWork($task);
        }

        if ($action === 'summary' && isset($input['partner_id'])) {
            return $this->partnerSummary($task, (int) $input['partner_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $pending = Booking::where('status', 'pending')->count();
        $today = Booking::whereDate('booked_at', today())->count();
        $msg = "{$today} booking(s) today, {$pending} pending — follow-ups available";
        return $this->markComplete($task, ['today' => $today, 'pending' => $pending, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = "Digest: {$results['summary']['today']} booking(s) today, {$results['summary']['pending']} pending, {$results['summary']['flags']} follow-up flag(s)";
        return $this->markComplete($task, $results);
    }

    protected function autoWork(): array
    {
        $todayCount = Booking::whereDate('booked_at', today())->count();
        $todayRevenue = Booking::whereDate('booked_at', today())->sum('amount');
        $pendingCount = Booking::where('status', 'pending')->count();

        $stalePending = Booking::with('travelPartner:id,name')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->orderBy('created_at')
            ->take(5)
            ->get(['id', 'travel_partner_id', 'customer_name', 'customer_phone', 'amount', 'created_at']);

        $noContact = Booking::whereDate('booked_at', today())
            ->where(function ($q) {
                $q->whereNull('customer_phone')->orWhere('customer_phone', '');
            })
            ->count();

        $partnerDigest = TravelPartner::withCount(['bookings' => fn($q) => $q->whereDate('booked_at', today())])
            ->get(['id', 'name'])
            ->map(fn($p) => [
                'partner' => $p->name,
                'bookings_today' => $p->bookings_count,
            ])
            ->filter(fn($p) => $p['bookings_today'] > 0)
            ->values()
            ->toArray();

        $flags = $stalePending->map(fn($b) => [
            'booking_id' => $b->id,
            'partner' => $b->travelPartner?->name ?? 'Unknown',
            'customer' => $b->customer_name ?? 'Unknown',
            'phone' => $b->customer_phone ?? '-',
            'amount' => $b->amount,
            'created' => $b->created_at?->toDateTimeString(),
            'reason' => 'Pending for more than 24 hours',
        ])->toArray();

        if ($noContact > 0) {
            $flags[] = ['reason' => "{$noContact} booking(s) today missing customer phone"];
        }

        $followUps = $this->followUpDrafts($flags);

        return [
            'summary' => [
                'today' => $todayCount,
                'today_revenue' => $todayRevenue,
                'pending' => $pendingCount,
                'flags' => count($flags),
            ],
            'partner_digest' => $partnerDigest,
            'flags' => $flags,
            'follow_up_drafts' => $followUps,
            'message' => "Digest: {$todayCount} booking(s) today, {$pendingCount} pending, " . count($flags) . ' flag(s)',
        ];
    }

    protected function partnerSummary(AiAgentTask $task, int $partnerId): AiAgentTask
    {
        $partner = TravelPartner::find($partnerId);
        if (!$partner) {
            return $this->markFailed($task, "Travel partner #{$partnerId} not found");
        }

        $bookings = Booking::with('user:id,name')
            ->where('travel_partner_id', $partnerId)
            ->latest()
            ->take(10)
            ->get(['id', 'user_id', 'customer_name', 'amount', 'status', 'booked_at']);

        $stats = [
            'total' => Booking::where('travel_partner_id', $partnerId)->count(),
            'today' => Booking::where('travel_partner_id', $partnerId)->whereDate('booked_at', today())->count(),
            'revenue' => Booking::where('travel_partner_id', $partnerId)->where('status', '!=', 'cancelled')->sum('amount'),
        ];

        return $this->markComplete($task, [
            'partner_id' => $partnerId,
            'partner' => $partner->name,
            'stats' => $stats,
            'recent_bookings' => $bookings->toArray(),
        ]);
    }

    protected function followUpDrafts(array $flags): array
    {
        if (empty($flags)) return [];

        try {
            $llm = $this->ai();
            $json = json_encode($flags, JSON_UNESCAPED_UNICODE);
            return $llm->generateJson(
                "You are a booking assistant for Nepal tour operators. For each flagged booking, draft a short polite follow-up message (English, 1-2 sentences) the operator could send to the customer.\nFlags: {$json}\n\nReturn JSON: [{\"booking_id\": id, \"message\": \"string\"}]"
            );
        } catch (\Exception $e) {
            Log::warning("Booking assistant LLM failed for follow-ups: " . $e->getMessage());
            return collect($flags)->map(fn($f) => [
                'booking_id' => $f['booking_id'] ?? null,
                'message' => 'Namaste! Tapaiko booking confirm garna kripaya haamilaai sam' . 'parka garnuhos.',
            ])->toArray();
        }
    }
}
