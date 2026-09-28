<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[
    Fillable([
        'name',
        'profile_photo_path',
        'email',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'temporary_password_expires_at',
        'activated_at',
        'disabled_at',
        'is_edit_locked',
        'edit_unlocked_until',
    ]),
]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'is_edit_locked' => 'boolean',
            'edit_unlocked_until' => 'datetime',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array(strtoupper((string) $this->role), $roles, true);
    }

    public function isEditLocked(): bool
    {
        return (bool) $this->is_edit_locked;
    }

    public function isEditOverrideActive(): bool
    {
        return ! $this->is_edit_locked &&
            $this->edit_unlocked_until !== null &&
            $this->edit_unlocked_until->isFuture();
    }

    public function canEditRecord(Model $record): bool
    {
        if ($record->getAttribute('is_locked')) {
            return false;
        }

        if ($this->hasRole('ADMINISTRATOR')) {
            return true;
        }

        if (! $this->hasRole('STAFF', 'RMEC')) {
            return false;
        }

        if ($this->isEditLocked()) {
            return false;
        }

        if ((int) $record->getAttribute('created_by') !== $this->id) {
            return false;
        }

        if ($this->isEditOverrideActive()) {
            return true;
        }

        return $record->created_at?->greaterThanOrEqualTo(now()->subDay()) ?? false;
    }

    public function temporaryPasswordExpired(): bool
    {
        if (! $this->must_change_password) {
            return false;
        }

        return $this->temporary_password_expires_at !== null &&
            $this->temporary_password_expires_at->isPast();
    }

    public function isAwaitingActivation(): bool
    {
        return $this->is_active &&
            $this->must_change_password &&
            $this->activated_at === null;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
