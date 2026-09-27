<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AccountDeletionService
{
    /**
     * Delete a user account with proper data handling.
     *
     * Strategy:
     * - CHECK: Pending transactions/blockers before deletion
     * - REVOKE: Authentication tokens, sessions
     * - DELETE: Ephemeral data (push tokens, social accounts, XP, achievements, contacts, chats)
     * - ANONYMIZE: Profile data, financial records (retain for audit)
     * - RETAIN: Reports (anonymized author), financial records (anonymized), audit logs
     *
     * Idempotent: Safe to call multiple times on the same user.
     */
    public function delete(User $user, ?string $reason = null): array
    {
        // Idempotency: if already anonymized, return success
        if ($user->email && str_ends_with($user->email, '@deleted.local')) {
            return [
                'success' => true,
                'message' => 'Account was already deleted.',
                'deleted_items' => [],
                'retained_items' => $this->retainedItems(),
            ];
        }

        $deletedItems = [];

        DB::beginTransaction();

        try {
            // 0. Pre-deletion checks: flag pending transactions
            $pendingIssues = $this->checkPendingTransactions($user);
            if (!empty($pendingIssues)) {
                Log::warning('Account deletion with pending transactions', [
                    'user_id' => $user->id,
                    'pending' => $pendingIssues,
                ]);
            }

            // 1. Revoke all tokens (prevents any further API access)
            $user->tokens()->delete();
            $deletedItems[] = 'auth_tokens';

            // 2. Delete sessions (force logout from all devices)
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $deletedItems[] = 'sessions';

            // 3. Delete social accounts (prevents social login)
            $user->socialAccounts()->delete();
            $deletedItems[] = 'social_accounts';

            // 4. Delete push tokens (stops push notifications)
            $user->pushTokens()->delete();
            $deletedItems[] = 'push_tokens';

            // 5. Delete moderator permissions (no longer a moderator)
            $user->moderatorPermissions()->delete();
            $deletedItems[] = 'moderator_permissions';

            // 6. Delete XP transactions (ephemeral gamification data)
            $user->xpTransactions()->delete();
            $deletedItems[] = 'xp_transactions';

            // 7. Detach achievements (keep achievement records, just unlink user)
            $user->achievements()->detach();
            $deletedItems[] = 'user_achievements';

            // 8. Delete active subscription (stops billing/premium features)
            $user->subscription()->delete();
            $deletedItems[] = 'active_subscription';

            // 9. Delete subscription payments (transaction records tied to user)
            $user->subscriptionPayments()->delete();
            $deletedItems[] = 'subscription_payments';

            // 10. Delete emergency contacts (private contact info)
            $user->emergencyContacts()->delete();
            $deletedItems[] = 'emergency_contacts';

            // 11. Delete SOS alerts and reports (safety records)
            $sosAlertIds = $user->sosAlerts()->pluck('id');
            if ($sosAlertIds->isNotEmpty()) {
                \App\Models\SosReport::whereIn('sos_alert_id', $sosAlertIds)->delete();
            }
            $user->sosAlerts()->delete();
            $deletedItems[] = 'sos_alerts';

            // 12. Delete legal document acceptances (no longer needed without account)
            $user->legalAcceptances()->delete();
            $deletedItems[] = 'legal_acceptances';

            // 13. Delete support conversations and messages
            $conversationIds = $user->supportConversations()->pluck('id');
            if ($conversationIds->isNotEmpty()) {
                \App\Models\SupportMessage::whereIn('support_conversation_id', $conversationIds)->delete();
                \App\Models\SupportSatisfaction::whereIn('support_conversation_id', $conversationIds)->delete();
                $user->supportConversations()->delete();
            }
            $deletedItems[] = 'support_conversations';

            // 14. Delete AI assistant chats (private conversation data)
            $user->assistantChats()->delete();
            $deletedItems[] = 'assistant_chats';

            // 15. Delete moderation queue entries
            $user->moderationQueues()->delete();
            $deletedItems[] = 'moderation_queues';

            // 16. Delete content violations (private moderation data)
            \App\Models\ContentViolation::where('user_id', $user->id)->delete();
            $deletedItems[] = 'content_violations';

            // 17. Delete moderation strikes
            \App\Models\ModerationStrike::where('user_id', $user->id)->delete();
            $deletedItems[] = 'moderation_strikes';

            // 18. Delete offer redemptions
            $user->offerRedemptions()->delete();
            $deletedItems[] = 'offer_redemptions';

            // 19. Delete report media files from storage, then delete records
            $reportIds = $user->reports()->pluck('id');
            if ($reportIds->isNotEmpty()) {
                $mediaFiles = \App\Models\ReportMedia::whereIn('report_id', $reportIds)->get();
                foreach ($mediaFiles as $media) {
                    if ($media->media_url) {
                        try {
                            $disk = Storage::disk('report-images');
                            if ($disk->exists($media->media_url)) {
                                $disk->delete($media->media_url);
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to delete report media file', [
                                'media_id' => $media->id,
                                'path' => $media->media_url,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                \App\Models\ReportMedia::whereIn('report_id', $reportIds)->delete();
            }
            $deletedItems[] = 'report_media_files';

            // 20. Delete report comments, reactions, confirmations
            if ($reportIds->isNotEmpty()) {
                \App\Models\ReportComment::whereIn('report_id', $reportIds)->delete();
                \App\Models\ReportReaction::whereIn('report_id', $reportIds)->delete();
                \App\Models\ReportConfirmation::whereIn('report_id', $reportIds)->delete();
            }
            $deletedItems[] = 'report_interactions';

            // 21. Delete report security logs
            DB::table('report_security_logs')->where('user_id', $user->id)->delete();
            $deletedItems[] = 'report_security_logs';

            // 22. Anonymize reports (keep content, remove author identity)
            $user->reports()->update([
                'user_id' => null,
            ]);
            $deletedItems[] = 'reports_anonymized';

            // 23. Delete place reviews
            $user->reviews()->delete();
            $deletedItems[] = 'place_reviews';

            // 24. Delete place corrections
            \App\Models\PlaceCorrection::where('user_id', $user->id)->delete();
            $deletedItems[] = 'place_corrections';

            // 25. Delete ad impressions and clicks
            $user->adImpressions()->delete();
            $user->adClicks()->delete();
            $deletedItems[] = 'ad_impressions_clicks';

            // 26. Delete ad fraud logs related to user's ad campaigns
            $campaignIds = DB::table('ad_impressions')->where('user_id', $user->id)->pluck('ad_campaign_id')
                ->merge(DB::table('ad_clicks')->where('user_id', $user->id)->pluck('ad_campaign_id'))
                ->unique();
            if ($campaignIds->isNotEmpty()) {
                DB::table('ad_fraud_logs')->whereIn('ad_campaign_id', $campaignIds)->delete();
            }
            $deletedItems[] = 'ad_fraud_logs';

            // 27. Delete idempotency keys
            \App\Models\IdempotencyKey::where('user_id', $user->id)->delete();
            $deletedItems[] = 'idempotency_keys';

            // 28. Anonymize wallet (zero balance, keep for audit trail)
            $wallet = $user->wallet;
            if ($wallet) {
                $wallet->update(['balance' => 0]);
                $deletedItems[] = 'wallet_balance_zeroed';
            }

            // 29. Anonymize coin transactions (keep for financial audit)
            $coinTxCount = $user->coinTransactions()->count();
            if ($coinTxCount > 0) {
                $user->coinTransactions()->update([
                    'description' => DB::raw("CONCAT('[deleted] ', COALESCE(description, ''))"),
                ]);
            }
            $deletedItems[] = 'coin_transactions_anonymized';

            // 30. Anonymize withdrawals (keep for financial audit)
            $withdrawalCount = $user->withdrawals()->count();
            if ($withdrawalCount > 0) {
                $user->withdrawals()->update([
                    'account_details' => '{}',
                ]);
            }
            $deletedItems[] = 'withdrawals_anonymized';

            // 31. Anonymize payment transactions (keep for financial audit)
            $paymentTxCount = \App\Models\PaymentTransaction::where('user_id', $user->id)->count();
            if ($paymentTxCount > 0) {
                \App\Models\PaymentTransaction::where('user_id', $user->id)->update([
                    'account_details' => '{}',
                    'metadata' => '{}',
                ]);
            }
            $deletedItems[] = 'payment_transactions_anonymized';

            // 32. Anonymize ad reward events (keep for revenue audit)
            $adRewardCount = $user->adRewardEvents()->count();
            if ($adRewardCount > 0) {
                $user->adRewardEvents()->update([
                    'metadata' => '{}',
                ]);
            }
            $deletedItems[] = 'ad_reward_events_anonymized';

            // 33. Delete fraud profile (no longer needed)
            $user->fraudProfile()?->delete();
            $deletedItems[] = 'fraud_profile';

            // 34. Anonymize partner payments
            $partnerPaymentCount = \App\Models\PartnerPayment::where('user_id', $user->id)->count();
            if ($partnerPaymentCount > 0) {
                \App\Models\PartnerPayment::where('user_id', $user->id)->update([
                    'metadata' => '{}',
                ]);
            }
            $deletedItems[] = 'partner_payments_anonymized';

            // 35. Anonymize the user profile (final step)
            $user->update([
                'name' => 'Deleted User',
                'email' => 'deleted_' . $user->id . '@deleted.local',
                'phone' => null,
                'avatar' => null,
                'bio' => null,
                'gender' => null,
                'interest' => null,
                'profile_completed' => false,
                'badges' => [],
                'expertise_regions' => [],
                'settings' => [],
                'verification_tick' => 'none',
                'is_verified' => false,
                'total_xp' => 0,
                'current_level' => 1,
                'total_reports' => 0,
                'approved_reports' => 0,
                'rejected_reports' => 0,
                'approval_rate' => 0,
                'rank' => null,
                'sos_false_count' => 0,
                'sos_restricted_until' => null,
                'suspended_until' => null,
                'last_contribution_at' => null,
            ]);
            $deletedItems[] = 'profile_anonymized';

            DB::commit();

            Log::info('Account deleted', [
                'user_id' => $user->id,
                'deleted_items' => $deletedItems,
                'pending_issues' => $pendingIssues,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'deleted_items' => $deletedItems,
                'retained_items' => $this->retainedItems(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Account deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Account deletion failed. Please try again or contact support.',
            ];
        }
    }

    /**
     * Check for pending transactions that may be affected by deletion.
     * Returns a list of issues (non-blocking, just logged for audit).
     */
    private function checkPendingTransactions(User $user): array
    {
        $issues = [];

        // Pending withdrawals
        $pendingWithdrawals = $user->withdrawals()
            ->whereIn('status', ['pending', 'processing'])
            ->count();
        if ($pendingWithdrawals > 0) {
            $issues[] = "{$pendingWithdrawals} pending withdrawal(s) will be cancelled";
        }

        // Active bookings
        $activeBookings = $user->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
        if ($activeBookings > 0) {
            $issues[] = "{$activeBookings} active booking(s) will be orphaned";
        }

        // Non-free subscription
        $subscription = $user->subscription;
        if ($subscription && $subscription->plan && $subscription->plan->slug !== 'free') {
            $issues[] = "Active premium subscription will be cancelled";
        }

        return $issues;
    }

    private function retainedItems(): array
    {
        return [
            'reports_anonymized' => 'Reports retained with anonymized author for community value',
            'coin_transactions_anonymized' => 'Financial records retained for audit compliance',
            'withdrawals_anonymized' => 'Withdrawal records retained for financial audit',
            'payment_transactions_anonymized' => 'Payment records retained for financial audit',
            'ad_reward_events_anonymized' => 'Revenue records retained for financial audit',
            'audit_logs_retained' => 'System audit logs retained for compliance',
        ];
    }
}
