<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class EmployeeSalary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_name',
        'position',
        'department',
        'base_salary',
        'bonus',
        'deductions',
        'net_salary',
        'currency',
        'payment_status',
        'payment_date',
        'period_start',
        'period_end',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('period_start', $month)
            ->whereYear('period_start', $year);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('period_start', Carbon::now()->month)
            ->whereYear('period_start', Carbon::now()->year);
    }

    public function calculateNetSalary(): float
    {
        return (float) $this->base_salary + (float) $this->bonus - (float) $this->deductions;
    }

    public function markAsPaid(): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_date' => Carbon::now(),
        ]);
    }
}
