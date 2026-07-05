<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function alerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Alert::class, 'created_by');
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PlaceReview::class);
    }

    public function socialAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function pushTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    public function moderatorPermissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModeratorPermission::class);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function xpTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    public function purchases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserPurchase::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserSubscription::class)->where('status', 'active');
    }

    public function hasPremiumFeature(string $feature): bool
    {
        $sub = $this->subscription;
        if (!$sub || !$sub->isActive()) return false;
        $features = $sub->plan->features ?? [];
        return in_array($feature, $features);
    }

    public function isPremium(): bool
    {
        return $this->subscription?->isActive() ?? false;
    }

    public function achievements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot(['unlocked_at', 'is_suspicious', 'suspicious_reason', 'flagged_by', 'cleared_at', 'cleared_by'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
            if (empty($user->role_id)) {
                $defaultRole = Role::where('is_default', true)->first();
                if ($defaultRole) {
                    $user->role_id = $defaultRole->id;
                }
            }
        });

        static::created(function (User $user) {
            $freePlan = SubscriptionPlan::where('slug', 'free')->first();
            if ($freePlan && !$user->subscription()->exists()) {
                UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null,
                ]);
            }
        });
    }

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'uuid',
        'avatar',
        'bio',
        'total_xp',
        'current_level',
        'verification_tick',
        'approved_reports',
        'rejected_reports',
        'is_verified',
        'status',
        'profile_completed',
        'badges',
        'expertise_regions',
        'settings',
        'total_reports',
        'approval_rate',
        'rank',
        'last_contribution_at',
    ];

    protected $appends = ['points'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'badges' => 'array',
            'expertise_regions' => 'array',
            'settings' => 'array',
            'is_verified' => 'boolean',
            'profile_completed' => 'boolean',
            'total_xp' => 'integer',
            'current_level' => 'integer',
            'total_reports' => 'integer',
            'approved_reports' => 'integer',
            'rejected_reports' => 'integer',
            'approval_rate' => 'decimal:2',
            'rank' => 'integer',
            'last_contribution_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isRegularUser(): bool
    {
        return $this->role?->name === 'user';
    }

    public function isModerator(): bool
    {
        return $this->role?->name === 'moderator';
    }

    public function promoteToAdmin(): bool
    {
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && $this->isRegularUser()) {
            $this->role_id = $adminRole->id;
            return $this->save();
        }
        return false;
    }

    public function promoteToModerator(): bool
    {
        $modRole = Role::where('name', 'moderator')->first();
        if ($modRole && $this->isRegularUser()) {
            $this->role_id = $modRole->id;
            return $this->save();
        }
        return false;
    }

    public function demoteToUser(): bool
    {
        $userRole = Role::where('name', 'user')->first();
        if ($userRole && ($this->isAdmin() || $this->isModerator())) {
            $this->role_id = $userRole->id;
            return $this->save();
        }
        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return $this->role?->hasPermission($permission) ?? false;
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->role?->name;
    }

    public function getPointsAttribute(): int
    {
        return (int) ($this->total_xp ?? 0);
    }
}

