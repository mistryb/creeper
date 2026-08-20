<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $creep_api_key
 * @property string|null $creep_api_key_hint
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CreepTarget> $creepTargets
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'creep_api_key', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'creep_api_key' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<CreepTarget, $this> */
    public function creepTargets(): HasMany
    {
        return $this->hasMany(CreepTarget::class);
    }

    /**
     * Whether this user has a model API key on file for the creeping agent.
     */
    public function hasCreepApiKey(): bool
    {
        return filled($this->creep_api_key);
    }

    /**
     * Store a model API key, keeping the last four characters in the clear so
     * the settings screen can identify it without decrypting anything.
     */
    public function setCreepApiKey(#[\SensitiveParameter] string $key): void
    {
        $this->forceFill([
            'creep_api_key' => $key,
            'creep_api_key_hint' => mb_substr($key, -4),
        ])->save();
    }

    public function forgetCreepApiKey(): void
    {
        $this->forceFill([
            'creep_api_key' => null,
            'creep_api_key_hint' => null,
        ])->save();
    }
}
