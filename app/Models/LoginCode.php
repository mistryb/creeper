<?php

namespace App\Models;

use Database\Factories\LoginCodeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An outstanding one-time code, keyed by the address it was sent to.
 *
 * @property string $email
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 */
#[UseFactory(LoginCodeFactory::class)]
class LoginCode extends Model
{
    /** @use HasFactory<LoginCodeFactory> */
    use HasFactory;

    protected $table = 'login_codes';

    protected $primaryKey = 'email';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Codes are written by the broker, never filled from a request. */
    protected $guarded = [];

    /** There is nothing to update after issuing, so no `updated_at`. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** @param  Builder<self>  $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
