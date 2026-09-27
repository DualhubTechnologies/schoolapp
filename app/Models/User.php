<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $email_verification_code hash of the code emailed at registration (App\Support\EmailVerificationCode)
 * @property Carbon|null $email_verification_sent_at
 * @property string $password
 * @property int|null $school_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'school_id', 'modules'])]
#[Hidden(['password', 'remember_token', 'email_verification_code'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'password' => 'hashed',
            // Modules chosen by the administrator; null = the role's defaults.
            'modules' => 'array',
        ];
    }

    /**
     * The school's plan caps how many staff logins it may have.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->school_id) {
                SubscriptionManager::ensureRoomForUsers((int) $user->school_id);
            }
        });
    }

    /**
     * The staff record this login belongs to (teachers, bursars...).
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Determine which Filament panel(s) this user may access.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole('Super Admin'),   // /admin — owner only
            'app' => true,                             // root — everyone else (super admin allowed too)
            default => false,
        };
    }

    /**
     * Get the school this user belongs to.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
