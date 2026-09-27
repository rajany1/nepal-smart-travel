<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PurgeExpiredData extends Command
{
    protected $signature = 'data:purge {--dry-run : Show what would be deleted without deleting}';
    protected $description = 'Purge expired data according to retention policies';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalPurged = 0;

        $this->info('Oripori Data Retention Purge' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->newLine();

        // 1. Purge expired SOS alerts (older than 90 days)
        $count = $this->purgeSosAlerts($dryRun);
        $totalPurged += $count;

        // 2. Purge old content violations (older than 1 year)
        $count = $this->purgeContentViolations($dryRun);
        $totalPurged += $count;

        // 3. Purge old moderation strikes (expired + older than 1 year)
        $count = $this->purgeModerationStrikes($dryRun);
        $totalPurged += $count;

        // 4. Purge old ad fraud logs (older than 1 year)
        $count = $this->purgeAdFraudLogs($dryRun);
        $totalPurged += $count;

        // 5. Purge old report security logs (older than 1 year)
        $count = $this->purgeReportSecurityLogs($dryRun);
        $totalPurged += $count;

        // 6. Purge old audit logs (older than 2 years)
        $count = $this->purgeAuditLogs($dryRun);
        $totalPurged += $count;

        // 7. Purge expired moderation queue items (older than 6 months)
        $count = $this->purgeModerationQueue($dryRun);
        $totalPurged += $count;

        // 8. Purge old AI assistant chats (older than 6 months)
        $count = $this->purgeAssistantChats($dryRun);
        $totalPurged += $count;

        // 9. Purge expired legal document acceptances (keep indefinitely for compliance)
        // NOT purged — legal acceptance records must be retained

        $this->newLine();
        $this->info("Total records purged: {$totalPurged}");

        Log::info('Data purge completed', [
            'dry_run' => $dryRun,
            'total_purged' => $totalPurged,
        ]);

        return self::SUCCESS;
    }

    private function purgeSosAlerts(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subDays(90);
        $query = DB::table('sos_alerts')
            ->where('status', '!=', 'active')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();
        if ($count > 0) {
            $this->info("  SOS alerts (resolved/cancelled/expired, older than 90 days): {$count}");
            if (!$dryRun) {
                $query->delete();
            }
        }
        return $count;
    }

    private function purgeContentViolations(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subYear();
        $count = DB::table('content_violations')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $this->info("  Content violations (older than 1 year): {$count}");
            if (!$dryRun) {
                DB::table('content_violations')->where('created_at', '<', $cutoff)->delete();
            }
        }
        return $count;
    }

    private function purgeModerationStrikes(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subYear();
        $count = DB::table('moderation_strikes')
            ->where('created_at', '<', $cutoff)
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                  ->orWhereNull('expires_at');
            })
            ->count();
        if ($count > 0) {
            $this->info("  Moderation strikes (expired, older than 1 year): {$count}");
            if (!$dryRun) {
                DB::table('moderation_strikes')
                    ->where('created_at', '<', $cutoff)
                    ->where(function ($q) {
                        $q->where('expires_at', '<', now())
                          ->orWhereNull('expires_at');
                    })
                    ->delete();
            }
        }
        return $count;
    }

    private function purgeAdFraudLogs(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subYear();
        $count = DB::table('ad_fraud_logs')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $this->info("  Ad fraud logs (older than 1 year): {$count}");
            if (!$dryRun) {
                DB::table('ad_fraud_logs')->where('created_at', '<', $cutoff)->delete();
            }
        }
        return $count;
    }

    private function purgeReportSecurityLogs(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subYear();
        $count = DB::table('report_security_logs')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $this->info("  Report security logs (older than 1 year): {$count}");
            if (!$dryRun) {
                DB::table('report_security_logs')->where('created_at', '<', $cutoff)->delete();
            }
        }
        return $count;
    }

    private function purgeAuditLogs(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subYears(2);
        $count = DB::table('audit_logs')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $this->info("  Audit logs (older than 2 years): {$count}");
            if (!$dryRun) {
                DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
            }
        }
        return $count;
    }

    private function purgeModerationQueue(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subMonths(6);
        $count = DB::table('moderation_queues')
            ->where('status', '!=', 'pending')
            ->where('created_at', '<', $cutoff)
            ->count();
        if ($count > 0) {
            $this->info("  Moderation queue (resolved, older than 6 months): {$count}");
            if (!$dryRun) {
                DB::table('moderation_queues')
                    ->where('status', '!=', 'pending')
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            }
        }
        return $count;
    }

    private function purgeAssistantChats(bool $dryRun): int
    {
        $cutoff = Carbon::now()->subMonths(6);
        $count = DB::table('assistant_chats')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $this->info("  AI assistant chats (older than 6 months): {$count}");
            if (!$dryRun) {
                DB::table('assistant_chats')->where('created_at', '<', $cutoff)->delete();
            }
        }
        return $count;
    }
}
