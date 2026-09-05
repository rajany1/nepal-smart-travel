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

    private ?array $canonIndexCache = null;

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

        // Pass 1: word-boundary matches (ASCII + Devanagari, unicode-safe)
        foreach ($this->wordlist() as $word => $severity) {
            if (preg_match_all('/(?<![\p{L}\p{N}_])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_])/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
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

        // Pass 2: obfuscated matches - "f u c k", "f.u.c.k", "f00k", "fUcK",
        // and romanized/Devanagari Nepali variants ("c h o d", "चो द", "चुद")
        $tokens = preg_split('/([^\p{L}\p{N}_]+)/u', $text, -1, PREG_SPLIT_OFFSET_CAPTURE);
        $n = count($tokens);
        $canonIndex = $this->canonicalIndex();
        for ($i = 0; $i < $n; $i++) {
            $raw = $tokens[$i][0];
            if ($raw === '') {
                continue;
            }
            $start = $tokens[$i][1];
            // Build a candidate by consuming ONLY short fragments (<=2 chars):
            // "f u c k", "fu" "ck", "चो" "द". Long tokens ("timi") never merge.
            // Stop as soon as a full dictionary word is formed.
            $decoded = '';
            $matchKey = null;
            $matchJ = -1;
            $k = $i;
            while ($k < $n && mb_strlen($tokens[$k][0]) <= 2 && mb_strlen($decoded) < 20) {
                $decoded .= $this->tokenKey($tokens[$k][0]);
                if (isset($canonIndex[$decoded])) {
                    $matchKey = $canonIndex[$decoded];
                    $matchJ = $k;
                    break;
                }
                $k++;
            }
            if ($matchKey !== null) {
                $end = $tokens[$matchJ][1] + strlen($tokens[$matchJ][0]);
                $span = substr($text, $start, $end - $start);
                $hits[] = [
                    'word' => $matchKey,
                    'severity' => $this->wordlist()[$matchKey],
                    'censored' => $this->censorWord($span),
                    'start' => $start,
                    'end' => $end,
                ];
                $i = $matchJ;
            }
            // no match: advance only one token — a match may start at the
            // next fragment ("it is f u c k" -> the "f u c k" group)
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
        foreach (mb_str_split($matched) as $ch) {
            if (preg_match('/[\p{L}]/u', $ch)) {
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
     * Canonical key used for lookups: ascii leet-form for romanized words,
     * consonant-only form for Devanagari (so चोद / चुद / चूद all collide).
     */
    private function tokenKey(string $raw): string
    {
        $ascii = $this->normalize($raw);
        if ($ascii !== '') {
            return $ascii;
        }
        return $this->canonicalDevanagari($raw);
    }

    /**
     * Reduce a Devanagari token to its consonants: matras (vowel signs),
     * halant, nukta and marks are dropped. "चोद", "चुद", "चूद", "चो द" all
     * become "चद" and match the same dictionary entry.
     */
    private function canonicalDevanagari(string $raw): string
    {
        $out = '';
        foreach (mb_str_split($raw) as $ch) {
            $code = mb_ord($ch);
            if ($code === false) {
                continue;
            }
            // consonants, nukta-form consonants, and independent vowels
            if ((0x0915 <= $code && $code <= 0x0939)
                || (0x0958 <= $code && $code <= 0x095F)
                || (0x0904 <= $code && $code <= 0x0914)) {
                $out .= $ch;
            }
        }
        return $out;
    }

    /**
     * word => canonical key map built from the active wordlist, used to
     * resolve obfuscated tokens back to their dictionary word.
     */
    private function canonicalIndex(): array
    {
        if ($this->canonIndexCache !== null) {
            return $this->canonIndexCache;
        }
        $map = [];
        foreach ($this->wordlist() as $word => $severity) {
            $key = $this->tokenKey($word);
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $word;
            }
        }
        return $this->canonIndexCache = $map;
    }

/**
     * Realtime guard. Censors, records, escalates.
     *
     * @return array{action: string, censored: string, warning: ?array, account: string, until: ?string}
     */
    public function guard(User $user, string $content, ?string $entityType = null, $entityId = null, ?string $field = null, string $source = 'live'): array
    {
        $result = $this->censor($content);
        if (empty($result['hits'])) {
            return $this->activeAction($content);
        }

        $user->refresh();
        if ($user->status === 'banned') {
            return $this->activeAction($result['text']) + [
                'action' => 'blocked',
                'account' => 'banned',
                'until' => null,
            ];
        }

        $this->recordViolation($user, $entityType, $entityId, $result['hits'], $content, $result['text'], $field, $source);

        $ladder = $this->applyStrikePolicy($user, $entityType);

        // System-generated account notice on escalation (warning / suspend / ban):
        // a targeted alert + push + Gmail email with full guidance.
        if (($ladder['level'] ?? 0) >= 1 && in_array($ladder['account'] ?? null, ['active', 'suspended', 'banned'])) {
            $level = $ladder['level'] === 3 ? 'block' : ($ladder['level'] === 2 ? 'suspend' : 'warning');
            $reason = $this->strikeReasonText($result['hits']);
            $title = match ($level) {
                'block' => 'Account Banned - Nepal Smart Travel',
                'suspend' => 'Account Suspended - Nepal Smart Travel',
                default => 'Content Warning - Nepal Smart Travel',
            };
            $link = null;
            if ($entityType && $entityId) {
                $link = ['type' => 'screen', 'value' => (string) $entityId];
            }
            app(AccountNoticeService::class)->deliver($user, $level, $reason, $title, $link ?? []);
        }

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
    private function recordViolation(User $user, ?string $entityType, $entityId, array $hits, string $original, string $censored, ?string $field = null, string $source = 'live'): void
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
            'field' => $field ?? 'content',
            'original_text' => mb_substr($original, 0, 500),
            'censored_text' => mb_substr($censored, 0, 500),
            'severity' => $hits[0]['severity'] ?? 'mild',
            'found_words' => array_map(fn($h) => $h['word'], $hits),
            'source' => $source,
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
     * Human-readable "reason" text from detected hit words, for the notice.
     *
     * @param  array  $hits  result of scan()/censor()
     */
    private function strikeReasonText(array $hits): string
    {
        $words = [];
        foreach ($hits as $h) {
            $words[] = (string) ($h['word'] ?? '');
        }
        $words = array_values(array_filter(array_unique($words)));
        if (empty($words)) {
            return 'Your content was flagged for violating community guidelines.';
        }
        return 'Flagged language in your recent content: ' . implode(', ', $words) . '.';
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
    public function manualStrike(User $user, string $level, string $reason, ?User $issuer = null): array
    {
        $user->refresh();
        ModerationStrike::create([
            'user_id' => $user->id,
            'level' => $level,
            'reason' => (string) $reason,
            'issued_by' => $issuer?->id,
            'source_type' => 'manual',
        ]);
        $result = $this->applyStrikePolicy($user);

        // System-generated account notice to the punished user.
        $title = match ($level) {
            'block' => 'Account Banned - Nepal Smart Travel',
            'suspend' => 'Account Suspended - Nepal Smart Travel',
            default => 'Content Warning - Nepal Smart Travel',
        };
        app(AccountNoticeService::class)->deliver($user, $level, (string) $reason, $title);

        return $result;
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
                        $this->recordViolation($user, $type, $pk, $d['hits'], $d['original'], $d['censored'], $field, 'sweep');
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