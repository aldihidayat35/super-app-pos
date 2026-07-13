<?php

namespace App\Models;

use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone_number',
        'avatar_path',
        'is_active',
        'last_login_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @return BelongsToMany<WorkLocation, $this>
     */
    public function workLocations(): BelongsToMany
    {
        return $this->belongsToMany(WorkLocation::class, 'user_work_locations')
            ->withPivot('is_default', 'effective_from', 'effective_until', 'is_active')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_users')
            ->withPivot('role', 'is_active', 'blocked_at', 'blocked_reason')
            ->withTimestamps();
    }

    public function hasUnrestrictedLocationScope(): bool
    {
        return $this->hasAnyRole(['super_admin', 'owner_viewer', 'owner_approver', 'admin_config']);
    }

    /**
     * @return list<int>
     */
    public function permittedWorkLocationIds(): array
    {
        if ($this->hasUnrestrictedLocationScope()) {
            return WorkLocation::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->workLocations()
            ->wherePivot('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('user_work_locations.effective_from')
                    ->orWhere('user_work_locations.effective_from', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('user_work_locations.effective_until')
                    ->orWhere('user_work_locations.effective_until', '>=', now()->toDateString());
            })
            ->pluck('work_locations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessWorkLocation(int $workLocationId): bool
    {
        return in_array($workLocationId, $this->permittedWorkLocationIds(), true);
    }
}
