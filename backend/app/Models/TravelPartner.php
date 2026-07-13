<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPartner extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'logo', 'phone', 'email',
        'website', 'address', 'district', 'commission_rate',
        'commission_fixed', 'value_npr', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'commission_fixed' => 'decimal:2',
            'value_npr' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class, 'business_id');
    }
}
