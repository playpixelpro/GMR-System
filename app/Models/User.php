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
        'email',
        'password',
        'role',
        'is_active',
        'must_change_password',
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

    public function canEditRecord(Model $record): bool
    {
        if ($this->hasRole('ADMINISTRATOR')) {
            return true;
        }

        return $record->created_at?->greaterThanOrEqualTo(now()->subDay()) &&
            (int) $record->getAttribute('created_by') === $this->id;
    }

    public function temporaryPasswordExpired(): bool
    {
        return $this->temporary_password_expires_at !== null &&
            $this->temporary_password_expires_at->isPast();
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
