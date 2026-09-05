<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Report;

class ExpireStaleReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Expire reports past their expires_at timestamp
        Report::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        // Auto-set expires_at for reports that don't have one yet, based on priority
        $expiryHours = [
            'critical' => 24,
            'high' => 48,
            'medium' => 168,  // 7 days
            'low' => 720,     // 30 days
        ];

        foreach ($expiryHours as $priority => $hours) {
            Report::where('status', 'approved')
                ->where('is_active', true)
                ->whereNull('expires_at')
                ->where('priority', $priority)
                ->update(['expires_at' => now()->addHours($hours)]);
        }
    }
}
