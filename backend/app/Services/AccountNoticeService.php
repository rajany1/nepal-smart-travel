<?php

namespace App\Services;

use App\Mail\AccountNoticeMail;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Delivers a system-generated, user-specific account notice (alert + push + email).
 *
 * Used by the content-safety escalation ladder so that the moment a user
 * receives a warning / suspension / ban, they are told immediately:
 *   - a targeted Alert row (sender_type = system) they can read in the app,
 *   - an FCM push, and
 *   - a Gmail SMTP email containing the full guidance.
 */
class AccountNoticeService
{
    public const SEVERITY_BY_LEVEL = [
        'warning' => 'info',
        'suspend' => 'high',
        'block' => 'critical',
    ];

    /**
     * Create + deliver the notice. Never throws — failures are logged so a
     * missing mail credential or FCM key can't break the strike action.
     *
     * @param  User    $user
     * @param  string  $level      warning|suspend|block|activate
     * @param  string  $reason     plain-language reason / guidance shown to user
     * @param  string  $title      short notice headline
     * @param  array   $link       ['type' => 'report'|'external'|'screen', 'value' => id|url]
     */
    public function deliver(User $user, string $level, string $reason, string $title, array $link = []): ?Alert
    {
        $severity = self::SEVERITY_BY_LEVEL[$level] ?? 'info';
        $guidance = $this->guidance($level, $reason);

        $alert = null;
        try {
            $alert = Alert::create([
                'uuid' => (string) Str::uuid(),
                'title' => $title,
                'description' => $guidance,
                'alert_type' => 'system',
                'severity' => $severity,
                'is_broadcast' => false,
                'target_user_id' => $user->id,
                'sender_type' => 'system',
                'link_type' => $link['type'] ?? null,
                'link_value' => $link['value'] ?? null,
                'created_by' => $user->id,
                'expires_at' => $level === 'block' ? null : now()->addDays(30),
            ]);
        } catch (\Throwable $e) {
            Log::error('AccountNotice: alert row failed: ' . $e->getMessage());
        }

        // Selective push (banned users cannot call APIs, but the push still lands).
        try {
            PushNotificationService::sendToUser(
                $user->id,
                $title,
                str($guidance)->limit(180),
                data: ['type' => 'account_notice', 'severity' => $severity],
                settingsKey: 'account_notices',
            );
        } catch (\Throwable $e) {
            Log::error('AccountNotice: push failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        // Gmail SMTP email with full guidance.
        try {
            Mail::to($user->email)->send(new AccountNoticeMail(
                subjectText: $title,
                title: $title,
                guidance: $guidance,
                severity: $severity,
            ));
        } catch (\Throwable $e) {
            Log::error('AccountNotice: email failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        return $alert;
    }

    /**
     * Composable, user-facing message including status + reason + steps.
     */
    public function guidance(string $level, string $reason): string
    {
        $head = match ($level) {
            'warning' => "A warning has been issued on your account for violating our community guidelines.",
            'suspend' => "Your account has been temporarily suspended for repeated violations of our community guidelines.",
            'block'   => "Your account has been permanently banned due to repeated violations of our community guidelines.",
            default   => "There has been an update on your account.",
        };

        $reasonText = trim((string) $reason);
        $reasonPart = $reasonText !== '' ? "\n\nReason: {$reasonText}" : '';

        return $head . $reasonPart . "\n\nWhat you can do: review our community guidelines, and contact support if you believe this was a mistake. Repeated violations may lead to permanent account removal.";
    }
}