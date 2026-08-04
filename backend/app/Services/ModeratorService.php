<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ModerationQueue;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ModeratorService
{
    public const PERMISSIONS = [
        'view_dashboard' => 'Access the admin dashboard overview',
        'approve_reports' => 'Approve or reject user reports',
        'delete_reports' => 'Delete reports from the system',
        'manage_places' => 'Create, edit, and delete places',
        'manage_alerts' => 'Create and delete alerts',
        'manage_users' => 'View list and manage user status',
        'assign_moderator' => 'Promote or demote moderators',
        'view_analytics' => 'View dashboard analytics and settings',
        'moderate_reviews' => 'Moderate place reviews and comments',
        'manage_achievements' => 'View and manage achievements and user progress',
    ];

    public function userHasPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    public function getPermissions(User $user): array
    {
        return $user->role?->permissions->pluck('name')->toArray() ?? [];
    }

    public function log(
        ?User $user = null,
        string $action = '',
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?string $description = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
        ]);
    }

    public function logSecurity(
        string $action,
        string $description,
        ?User $user = null,
        ?array $extra = null,
    ): AuditLog {
        $ip = Request::ip();
        $now = now();

        // Write first, then count so the current attempt is included (BE-26)
        $entry = $this->log($user, $action, null, null, $description, array_merge([
            'ip' => $ip,
            'user_agent' => Request::userAgent(),
            'suspicious' => false,
        ], $extra ?? []));

        $threshold = match ($action) {
            // Suspicious: 5+ failed logins from same IP in 15 minutes
            'security.login-failed' => 5,
            // Suspicious: 10+ unauthorized access attempts from same IP in 1 hour
            'security.unauthorized-access' => 10,
            // Suspicious: 10+ permission denials from same IP in 1 hour
            'security.permission-denied' => 10,
            default => null,
        };

        if ($threshold === null) {
            return $entry;
        }

        $since = $action === 'security.login-failed'
            ? $now->copy()->subMinutes(15)
            : $now->copy()->subHour();

        $recentCount = AuditLog::where('action', $action)
            ->where('ip_address', $ip)
            ->where('created_at', '>=', $since)
            ->count();

        if ($recentCount >= $threshold) {
            $reason = match ($action) {
                'security.login-failed' => "{$recentCount} failed logins from {$ip} in 15 minutes",
                'security.unauthorized-access' => "{$recentCount} unauthorized access attempts from {$ip} in 1 hour",
                default => "{$recentCount} permission denials from {$ip} in 1 hour",
            };

            $entry->update([
                'metadata' => array_merge($entry->metadata ?? [], [
                    'suspicious' => true,
                    'suspicious_reason' => $reason,
                ]),
            ]);
        }

        return $entry;
    }

    public function addToModerationQueue(
        string $contentType,
        int $contentId,
        int $submittedBy,
        string $priority = 'medium',
        float $aiSpamScore = 0.0,
    ): ModerationQueue {
        return ModerationQueue::create([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'submitted_by' => $submittedBy,
            'ai_spam_score' => $aiSpamScore,
            'priority' => $priority,
            'status' => 'pending',
        ]);
    }

    public function reviewQueueItem(
        ModerationQueue $item,
        User $reviewer,
        string $status,
        ?string $rejectionReason = null,
    ): void {
        $item->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        $this->log(
            $reviewer,
            "moderation.{$status}",
            $item->content_type,
            $item->content_id,
            "{$status} {$item->content_type} #{$item->content_id}",
        );
    }
}
