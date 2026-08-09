<?php

namespace App\Services;

use App\Models\BadWord;
use App\Models\ContentViolation;
use App\Models\GameSetting;
use App\Models\ModerationStrike;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Review AI Agent - content safety engine.
 *
 * 24/7 client that:
 *  - scans user-generated content for inappropriate words (real-time + sweep)
 *  - censors them in the database (f**k style) so the app stays usable & safe
 *  - escalates: warning -> temporary suspension -> permanent block
 *  - keeps a full accountability record (content_violations + moderation_strikes)
 *    so admins can see exactly who did what and why they were punished.
 */
class ContentSafetyService
{
    private ?array $wordlistCache = null;

    private const LEET = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
        '8' => 'b', '2' => 'z', '6' => 'g', '9' => 'g', '@' => 'a', '$' => 's',
        '!' => 'i', '+' => 't',
    ];

/**
     * @return array<array{word: string, severity: string, censored: string, start: int, end: int}>
     */
    public function scan(string $text): array
    {
        $text = (string) $text;
        $text = preg_replace('/\x{a0}/u', ' ', $text);
        if (trim($text) === '') {
            return [];
        }

        $hits = [];

        // Pass 1: clean word-boundary matches
        foreach ($this->wordlist() as $word => $severity) {
            if (preg_match_all('/\b' . preg_quote($word, '/') . '\b/i', $text, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$matched, $offset]) {
                    $hits[] = [
                        'word' => $word,
                        'severity' => $severity,
                        'censored' => $this->censorWord($matched),
                        'start' => $offset,
                        'end' => $offset + strlen($matched),
                    ];
                }
            }
        }

        // Pass 2: obfuscated matches - "f u c k", "f.u.c.k", "f00k", "fUcK"
        $tokens = preg_split('/([^a-zA-Z0-9]+)/', $text, -1, PREG_SPLIT_OFFSET_CAPTURE);
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $raw = $tokens[$i][0];
            if ($raw === '') {
                continue;
            }
            $start = $tokens[$i][1];
            $decoded = $this->normalize($raw);
            if ($decoded === '') {
                continue;
            }
            // merge consecutive short naked tokens ("f" "u" "c" "k", "fu" "ck")
            $j = $i;
            while ($j + 1 < $n && strlen($tokens[$j + 1][0]) <= 2 && strlen($decoded . $this->normalize($tokens[$j + 1][0])) <= 20) {
                $j++;
                $decoded .= $this->normalize($tokens[$j][0]);
            }
            if ($decoded !== '' && isset($this->wordlist()[$decoded])) {
                $end = ($j > $i)
                    ? $tokens[$j][1] + strlen($tokens[$j][0])
                    : $start + strlen($raw);
                $span = substr($text, $start, $end - $start);
                $hits[] = [
                    'word' => $decoded,
                    'severity' => $this->wordlist()[$decoded],
                    'censored' => $this->censorWord($span),
                    'start' => $start,
                    'end' => $end,
                ];
            }
            $i = max($i, $j);
        }

        // dedupe overlapping spans
        usort($hits, fn($a, $b) => $a['start'] <=> $b['start']);
        $clean = [];
        $lastEnd = -1;
        foreach ($hits as $h) {
            if ($h['start'] >= $lastEnd) {
                $clean[] = $h;
                $lastEnd = $h['end'];
            }
        }

        return $clean;
    }

    /**
     * Replace every bad word in $text with its censored form.
     *
     * @return array{text: string, hits: array}
     */
    public function censor(string $text): array
    {
        $hits = $this->scan($text);
        if (empty($hits)) {
            return ['text' => $text, 'hits' => []];
        }
        usort($hits, fn($a, $b) => $b['start'] <=> $a['start']);
        $out = $text;
        foreach ($hits as $h) {
            $out = substr_replace($out, $h['censored'], $h['start'], $h['end'] - $h['start']);
        }
        return ['text' => $out, 'hits' => $hits];
    }

    /**
     * Build the censored form of a matched word (e.g. "fuck" -> "f**k").
     */
    public function censorWord(string $matched): string
    {
        if ($matched === '') {
            return '';
        }
        $keep = $this->censorKeep();
        $out = '';
        $letters = 0;
        foreach (mb_str_split($matched) as $i => $ch) {
            if (preg_match('/[a-z]/i', $ch)) {
                $letters++;
                $out .= ($letters > $keep) ? '*' : $ch;
            } else {
                $out .= $ch;
            }
        }
        return $out;
    }

    private function censorWordCount(): int
    {
        return (int) GameSetting::getValue('safety_censor_keep_first', 1);
    }

    public function wordlist(): array
    {
        if ($this->wordlistCache !== null) {
            return $this->wordlistCache;
        }
        return $this->wordlistCache = BadWord::where('active', true)
            ->pluck('severity', 'word')
            ->all();
    }

    public function normalize(string $token): string
    {
        $token = mb_strtolower($token);
        $out = '';
        foreach (mb_str_split($token) as $ch) {
            $out .= self::LEET[$ch] ?? $ch;
        }
        return preg_replace('/[^a-z]/', '', $out);
    }

/**
     * Realtime guard. Censors, records, escalates.
     *
     * @return array{action: string, censored: string, warning: ?array, account: string, until: ?string}
     */
    public function guard(User $user, string $content, ?string $entityType = null, $entityId = null): array
    {
        $result = $this->censor($content);
        if (empty($result['hits'])) {
            return $this->cleanResult($content);
        }

        $user->refresh();
        if ($user->status === 'banned') {
            return $this->cleanResult($result['text']) + [
                'action' => 'blocked',
                'account' => 'banned',
                'until' => null,
            ];
        }

        $this->recordViolation($user, $entityType, $entityId, $result['hits'], $content, $result['text'], 'live');

        $ladder = $this->applyStrikePolicy($user, $entityType);

        return [
            'action' => 'censored',
            'censored' => $result['text'],
            'warning' => $ladder['warning'],
            'account' => $ladder['account'],
            'until' => $ladder['until'],
        ];
    }

    /**
     * Persistent record of what was said, what it became, and at what severity.
     */
    private function recordViolation(User $user, ?string $entityType, $entityId, array $hits, string $original, string $censored, string $source = 'live'): void
    {
        $map = [
            'PlaceReview' => 'review',
            'Report' => 'report',
            'ReportComment' => 'report_comment',
            'PlaceCorrection' => 'correction',
            'Alert' => 'alert',
            'RewardOffer' => 'offer',
        ];
        ContentViolation::create([
            'user_id' => $user->id,
            'entity_type' => $map[$entityType] ?? ($entityType ?? 'content'),
            'entity_id' => $entityId,
            'original_text' => mb_substr($original, 0, 500),
            'censored_text' => mb_substr($censored, 0, 500),
            'severity' => 'mild',
            'bad_words' => array_map(fn($h) => $h['word'], $hits),
            'source' => $entityType ?? 'content',
        ]);
    }

    /**
     * The escalation ladder. Every strike adds up:
     *   1st -> warning, 2nd -> 1-day suspension, 3rd+ -> permanent ban.
     *
     * @return array{warning: ?array, account: string, until: ?string, strikes: int, level: int}
     */
    public function applyStrikePolicy(User $user, ?string $sourceType = null): array
    {
        $strikes = $this->activeStrikes($user);

        $level = 0;
        if ($strikes >= $this->banAtStrikes()) {
            $level = 3;
        } elseif ($strikes === $this->suspendAtStrikes()) {
            $level = 2;
        } elseif ($strikes >= $this->warnAtStrikes()) {
            $level = 1;
        }

        $account = 'active';
        $until = null;
        $warning = null;

        if ($level === 1) {
            $account = 'active';
            $warning = 'Your content was flagged. Continue using clean language or you will be suspended.';
        } elseif ($level === 2) {
            $days = (int) GameSetting::getValue('safety_suspend_days', 1);
            $until = Carbon::now()->addDays($days);
            $user->update(['status' => 'suspended', 'suspended_until' => $until]);
            $account = 'suspended';
            $warning = 'You have been temporarily suspended for repeated policy violations.';
        } elseif ($level === 3) {
            $user->update(['status' => 'banned', 'suspended_until' => null]);
            $account = 'banned';
            $warning = 'Your account has been permanently banned.';
        }

        return [
            'warning' => $warning,
            'level' => $level,
            'account' => $account,
            'until' => $until ? $until->toDateTimeString() : null,
            'strikes' => $strikes,
        ];
    }

    private function warnAtStrikes(): int
    {
        return (int) GameSetting::getValue('safety_warn_at_strikes', 1);
    }

    private function suspendAtStrikes(): int
    {
        return (int) GameSetting::getValue('safety_suspend_at_strikes', 2);
    }

    private function banAtStrikes(): int
    {
        return (int) GameSetting::getValue('safety_ban_at_strikes', 3);
    }

    private function censorKeep(): int
    {
        return (int) GameSetting::getValue('safety_censor_keep_first', 1);
    }

    private function activeAction(string $content): array
    {
        return [
            'action' => 'pass',
            'censored' => $content,
            'warning' => null,
            'account' => 'active',
            'until' => null,
        ];
    }

    public function activeStrikes(User $user, ?int $windowDays = null): int
    {
        $windowDays ??= (int) GameSetting::getValue('safety_strike_window_days', 30);
        return ModerationStrike::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays($windowDays))
            ->count();
    }

/**
     * Condense guard() results into the payload to attach to API responses.
     */
    public function payload(array $guards): array
    {
        $censored = false;
        $warning = null;
        $account = 'active';
        $until = null;
        foreach ($guards as $g) {
            if ($g['action'] === 'censored') {
                $censored = true;
                $warning ??= $g['warning'];
            }
            if (in_array($g['account'], ['suspended', 'banned'])) {
                $account = $g['account'];
                $until ??= $g['until'];
            }
        }
        return [
            'censored' => $censored,
            'warning' => $warning,
            'account' => $account,
            'until' => $until,
        ];
    }

    /**
     * Manual strike (admin penalty, or scheduler reprimand).
     */
    public function manualStrike(User $user, string $note, ?string $entityType = null): array
    {
        $user->refresh();
        $strike = ModerationStrike::create([
            'user_id' => $user->id,
            'reason' => 'manual:' . ($note ?? 'manual'),
        ]);
        return $this->applyStrikePolicy($user, $entityType);
    }

    /**
     * Lift a suspension before it expires (admin action).
     */
    public function activate(User $user): void
    {
        $user->update(['status' => 'active', 'suspended_until' => null]);
    }

    /**
     * 24/7 agent sweep - censors anything the realtime guard missed
     * (disguised words, older content) and keeps the ladder enforced.
     */
    public function sweepBatch(int $limit = 30): array
    {
        $report = ['scanned' => 0, 'censored' => 0, 'violations' => 0, 'reactivated' => 0];
        $report['reactivated'] = $this->reactivateExpiredSuspensions();

        $entities = [
            ['place_review', \App\Models\PlaceReview::class, ['title', 'description']],
            ['report', \App\Models\Report::class, ['title', 'description']],
            ['report_comment', \App\Models\ReportComment::class, ['content']],
            ['alert', \App\Models\Alert::class, ['title', 'description']],
            ['place_correction', \App\Models\PlaceCorrection::class, ['place_name', 'description', 'suggested_name']],
            ['reward_offer', \App\Models\RewardOffer::class, ['title', 'description', 'terms']],
            ['ad_campaign', \App\Models\AdCampaign::class, ['name', 'content']],
        ];

        foreach ($entities as [$type, $model, $fields]) {
            $table = (new $model())->getTable();
            $rows = DB::table($table)
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $pk = $row->id;
                $userField = match ($type) {
                    'place_review', 'report', 'report_comment' => 'user_id',
                    'alert' => 'created_by',
                    'place_correction' => 'user_id',
                    'reward_offer', 'ad_campaign' => 'business_id',
                };
                $userId = $row->{$userField} ?? null;
                if (!$userId) {
                    continue;
                }
                $user = User::where('id', $userId)->first();
                if (!$user) {
                    continue;
                }

                $dirty = [];
                foreach ($fields as $field) {
                    $value = $row->{$field} ?? null;
                    if ($value === null || trim((string) $value) === '') {
                        continue;
                    }
                    $cacheKey = "safety_swept_{$type}_{$pk}_{$field}_{$row->updated_at}";
                    if (Cache::get($cacheKey)) {
                        continue;
                    }
                    $report['scanned']++;
                    $result = $this->censor((string) $value);
                    if (empty($result['hits'])) {
                        Cache::put($cacheKey, 1, now()->addDay());
                        continue;
                    }
                    $dirty[$field] = [
                        'original' => (string) $value,
                        'censored' => $result['text'],
                        'hits' => $result['hits'],
                    ];
                }

                if (!empty($dirty)) {
                    $updates = [];
                    foreach ($dirty as $field => $d) {
                        $updates[$field] = $d['censored'];
                        $this->recordViolation($user, $type, $pk, $d['hits'], $d['original'], $d['censored'], 'sweep');
                    }
                    DB::table($table)->where('id', $pk)->update($updates);
                    $report['censored']++;
                    $report['violations']++;
                }
            }
        }

        return $report;
    }

    public function reactivateExpiredSuspensions(): int
    {
        return User::where('status', 'suspended')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', Carbon::now())
            ->update(['status' => 'active', 'suspended_until' => null]);
    }
}