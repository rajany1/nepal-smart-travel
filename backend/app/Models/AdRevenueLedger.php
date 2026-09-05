<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdRevenueLedger extends Model
{
    protected $table = 'ad_revenue_ledger';

    protected $fillable = [
        'ad_campaign_id',
        'report_id',
        'context',
        'gross_amount',
        'user_share',
        'admin_share',
        'event_type',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'user_share' => 'float',
        'admin_share' => 'float',
    ];

    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get total admin revenue for a period.
     */
    public static function getAdminRevenue(?string $period = null): float
    {
        $query = self::query();
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }
        return (float) $query->sum('admin_share');
    }

    /**
     * Get total user payouts for a period.
     */
    public static function getUserPayouts(?string $period = null): float
    {
        $query = self::query();
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }
        return (float) $query->sum('user_share');
    }

    /**
     * Get revenue breakdown by context.
     */
    public static function getRevenueByContext(?string $period = null): array
    {
        $query = self::query()
            ->selectRaw('context, SUM(gross_amount) as gross, SUM(user_share) as user_paid, SUM(admin_share) as admin_kept, COUNT(*) as events');

        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }

        return $query->groupBy('context')->get()->toArray();
    }
}
