<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[
    Fillable([
        'name',
        'profile_photo_path',
        'email',
        'password',
        'role',
        'branch_id',
        'is_active',
        'must_change_password',
        'temporary_password_expires_at',
        'activated_at',
        'disabled_at',
        'is_edit_locked',
        'edit_unlocked_until',
        'registration_expires_at',
        'registration_confirmed_at',
    ]),
]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'branch_id' => 'integer',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'is_edit_locked' => 'boolean',
            'edit_unlocked_until' => 'datetime',
            'registration_expires_at' => 'datetime',
            'registration_confirmed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function isRegistrationPending(): bool
    {
        return $this->registration_expires_at !== null &&
            $this->registration_confirmed_at === null;
    }

    public function isRegistrationExpired(): bool
    {
        return $this->isRegistrationPending() &&
            $this->registration_expires_at->isPast();
    }

    /**
     * Resolve the user's avatar URL using the priority:
     * local uploaded avatar -> Gravatar -> null (render default initials).
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->profile_photo_path) {
                return Storage::disk('public')->url($this->profile_photo_path);
            }

            return $this->gravatarUrl();
        });
    }

    /**
     * Build a Gravatar avatar URL from the normalized email address.
     * Returns null when Gravatar is disabled or the user has no email.
     */
    public function gravatarUrl(?int $size = null): ?string
    {
        if (! config('services.gravatar.enabled')) {
            return null;
        }

        $hash = $this->gravatarEmailHash();

        if ($hash === null) {
            return null;
        }

        $query = http_build_query([
            's' => $size ?? config('services.gravatar.size', 80),
            'd' => config('services.gravatar.default', '404'),
            'r' => 'g',
        ]);

        return "https://www.gravatar.com/avatar/{$hash}?{$query}";
    }

    /**
     * SHA-256 hash of the email, normalized by trimming and lowercasing.
     */
    public function gravatarEmailHash(): ?string
    {
        $email = trim(strtolower((string) $this->email));

        return $email === '' ? null : hash('sha256', $email);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
