<?php

namespace App\Models;

use App\Enums\CreepProvider;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $pending_email An address awaiting confirmation by code.
 * @property string|null $password Retired. Sign-in is by one-time code.
 * @property string|null $remember_token
 * @property int|null $current_business_id The business picked in the sidebar chooser.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Business> $businesses
 * @property-read Business|null $currentBusiness
 * @property-read Collection<int, ApiKey> $apiKeys
 */
#[Fillable(['name', 'email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Businesses go by database cascade, which fires no model events, so
     * they are deleted one by one first — each takes its analyses with it.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->businesses()->get()->each->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * The account for an address, created if this is the first time we have
     * seen it.
     *
     * Only ever called once a one-time code has been redeemed, so reaching
     * here is proof that somebody reads mail at this address. That is why the
     * account is born verified.
     */
    public static function forEmail(string $email): self
    {
        $email = Str::lower(trim($email));

        $user = static::query()->firstOrCreate(
            ['email' => $email],
            ['name' => static::nameFromEmail($email)],
        );

        /*
         * Force filled rather than passed above: `email_verified_at` is not
         * mass assignable, so handing it to firstOrCreate would drop it on the
         * floor. This also verifies accounts that predate code sign-in, the
         * first time they come back.
         */
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    /**
     * A first guess at somebody's name, taken from their address, because the
     * sign-in form only ever asks for an address.
     *
     * "jane.doe+shopping@example.com" becomes "Jane Doe". It is a placeholder
     * and is meant to be corrected on the profile screen; when the local part
     * carries no names to find, the address itself is the honest answer.
     */
    public static function nameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');

        // Everything after a "+" is a tag for the mailbox, not part of a name.
        $local = Str::before($local, '+');

        $words = collect(preg_split('/[._\-]+/', $local) ?: [])
            ->filter(fn (string $word): bool => $word !== '' && ! ctype_digit($word))
            ->map(fn (string $word): string => Str::ucfirst(Str::lower($word)));

        return $words->isEmpty()
            ? $email
            : Str::limit($words->implode(' '), 255, '');
    }

    /**
     * The businesses this account watches competitors for, in the order they
     * were set up.
     *
     * @return HasMany<Business, $this>
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class)->orderBy('id');
    }

    /**
     * The business last picked in the sidebar chooser. Null until one has
     * been picked, or once the picked one has been deleted — use
     * {@see selectedBusiness()} for the one the app should show.
     *
     * @return BelongsTo<Business, $this>
     */
    public function currentBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'current_business_id');
    }

    /**
     * The business the app is working on: the one picked in the chooser, or
     * the first one set up when nothing has been picked. Null only for an
     * account with no businesses at all.
     */
    public function selectedBusiness(): ?Business
    {
        return $this->currentBusiness ?? $this->businesses()->first();
    }

    /**
     * Make a business the one the app is working on.
     */
    public function switchBusiness(Business $business): void
    {
        $this->forceFill(['current_business_id' => $business->id])->save();

        $this->setRelation('currentBusiness', $business);
    }

    /**
     * The keys this user has on file, newest last so the list reads in the
     * order they were added.
     *
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class)->orderBy('id');
    }

    /**
     * Store a model API key, keeping the last four characters in the clear so
     * the settings screen can identify it without decrypting anything.
     */
    public function addApiKey(string $name, CreepProvider $provider, #[\SensitiveParameter] string $key): ApiKey
    {
        return $this->apiKeys()->create([
            'name' => $name,
            'provider' => $provider,
            'key' => $key,
            'hint' => mb_substr($key, -4),
        ]);
    }
}
