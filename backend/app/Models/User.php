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

    public function moderationStrikes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModerationStrike::class, 'user_id');
    }

    public function contentViolations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContentViolation::class, 'user_id');
    }

    public function fraudProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserFraudProfile::class);
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

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OriporiCoinWallet::class);
    }

    public function coinTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function xpTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sosAlerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SosAlert::class);
    }

    public function activeSosAlert(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SosAlert::class)->where('status', 'active');
    }

    public function emergencyContacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmergencyContact::class);
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
        $sub = $this->subscription;
        if (!$sub || !$sub->isActive()) return false;

        // The auto-assigned free plan is not premium
        $slug = $sub->plan?->slug;
        if ($slug === 'free' || $slug === null) return false;

        return true;
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
        'name',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'phone_changed_at',
        'email_changed_at',
        'password',
        'uuid',
        'avatar',
        'bio',
        'gender',
        'interest',
        'verification_tick',
        'is_verified',
        'suspended_until',
        'profile_completed',
        'sos_false_count',
        'sos_restricted_until',
        'badges',
        'expertise_regions',
        'settings',
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
            'phone_verified_at' => 'datetime',
            'phone_changed_at' => 'datetime',
            'email_changed_at' => 'datetime',
            'password' => 'hashed',
            'badges' => 'array',
            'expertise_regions' => 'array',
            'settings' => 'array',
            'is_verified' => 'boolean',
            'profile_completed' => 'boolean',
            'sos_restricted_until' => 'datetime',
            'total_xp' => 'integer',
            'current_level' => 'integer',
            'total_reports' => 'integer',
            'approved_reports' => 'integer',
            'rejected_reports' => 'integer',
            'approval_rate' => 'decimal:2',
            'rank' => 'integer',
            'last_contribution_at' => 'datetime',
            'suspended_until' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role?->name, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === 'super_admin';
    }

    public function isRegularUser(): bool
    {
        return $this->role?->name === 'user';
    }

    public function isModerator(): bool
    {
        return $this->role?->name === 'moderator';
    }

    public function isBusiness(): bool
    {
        return $this->role?->name === 'business';
    }

    public function getRoleLevel(): int
    {
        return match($this->role?->name) {
            'super_admin' => 4,
            'admin' => 3,
            'moderator' => 2,
            'business' => 1,
            default => 0,
        };
    }

    public function canManageUser(User $target): bool
    {
        if ($this->id === $target->id) return false;

        $myLevel = $this->getRoleLevel();
        $targetLevel = $target->getRoleLevel();

        if ($this->isSuperAdmin()) {
            return $targetLevel < 4;
        }

        if ($this->isAdmin()) {
            return $targetLevel < 3;
        }

        if ($this->isModerator()) {
            return $targetLevel < 2;
        }

        return false;
    }

    public function business(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TravelPartner::class, 'user_id');
    }

    public function offerRedemptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OfferRedemption::class);
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

