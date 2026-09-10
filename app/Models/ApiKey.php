<?php

namespace App\Models;

use App\Enums\CreepProvider;
use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One model API key on a user's keyring.
 *
 * Keys live here and nowhere else — there is no key in the environment for
 * Creeper to fall back on. Each target picks the key it spends, so a user can
 * keep one key for the shopping they do on their own account and another for
 * work, and see which is which without ever seeing either again.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property CreepProvider $provider
 * @property string $key
 * @property string $hint The last four characters, in the clear.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, CreepTarget> $creepTargets
 */
#[Fillable(['name', 'provider', 'key', 'hint'])]
#[Hidden(['key'])]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => 'encrypted',
            'provider' => CreepProvider::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CreepTarget, $this> */
    public function creepTargets(): HasMany
    {
        return $this->hasMany(CreepTarget::class);
    }

    /**
     * How the key is described wherever one has to be picked out of a list.
     */
    public function label(): string
    {
        return "{$this->name} · {$this->provider->label()} ••••{$this->hint}";
    }
}
