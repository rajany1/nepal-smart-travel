<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PlatformExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'provider',
        'amount',
        'currency',
        'billing_cycle',
        'next_renewal_date',
        'last_paid_date',
        'status',
        'notes',
        'renewal_alert_sent',
        'alert_days_before',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'next_renewal_date' => 'date',
        'last_paid_date' => 'date',
        'renewal_alert_sent' => 'boolean',
        'alert_days_before' => 'integer',
        'metadata' => 'array',
    ];

    const CATEGORIES = [
        'hosting' => 'Hosting',
        'server' => 'Server',
        'database' => 'Database',
        'domain' => 'Domain',
        'map_api' => 'Map API',
        'email' => 'Email',
        'sms' => 'SMS',
        'storage' => 'Storage',
        'cdn' => 'CDN',
        'ai_api' => 'AI API',
        'apple_developer' => 'Apple Developer',
        'google_play' => 'Google Play',
        'advertising' => 'Advertising',
        'maintenance' => 'Maintenance',
        'employee_salary' => 'Employee Salary',
        'other' => 'Other',
    ];

    const BILLING_CYCLES = [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'one_time' => 'One Time',
        'pay_as_you_go' => 'Pay as You Go',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRenewalSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->where('next_renewal_date', '<=', Carbon::now()->addDays($days))
            ->where('next_renewal_date', '>=', Carbon::now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('next_renewal_date', '<', Carbon::now());
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);
    }

    public function getMonthlyEquivalentAttribute(): float
    {
        return match($this->billing_cycle) {
            'monthly' => (float) $this->amount,
            'yearly' => (float) $this->amount / 12,
            default => (float) $this->amount,
        };
    }

    public function getIsRenewalSoonAttribute(): bool
    {
        if (!$this->next_renewal_date) return false;
        return $this->next_renewal_date->diffInDays(Carbon::now()) <= $this->alert_days_before
            && $this->next_renewal_date->isFuture();
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->next_renewal_date) return false;
        return $this->next_renewal_date->isPast();
    }

    public function markAsPaid(): void
    {
        $this->update([
            'last_paid_date' => Carbon::now(),
            'renewal_alert_sent' => false,
            'next_renewal_date' => $this->calculateNextRenewal(),
        ]);
    }

    protected function calculateNextRenewal(): ?Carbon
    {
        if (!$this->next_renewal_date) return null;

        return match($this->billing_cycle) {
            'monthly' => $this->next_renewal_date->addMonth(),
            'yearly' => $this->next_renewal_date->addYear(),
            default => null,
        };
    }
}
