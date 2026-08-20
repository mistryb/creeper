<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects URLs that point somewhere they shouldn't.
 *
 * Creeper never fetches these itself — the agent does — but a self-hosted
 * agent usually sits inside a private network, which turns a user-supplied
 * URL into an SSRF vector. Blocking at the point of entry is the cheap fix.
 *
 * If a hostname can't be resolved at all we let it through: an unresolvable
 * host is unreachable for the agent too, and failing validation on a flaky
 * DNS lookup would be worse than the risk it avoids.
 */
class PublicUrl implements ValidationRule
{
    /**
     * Hostnames that never need a DNS lookup to know they're local.
     *
     * @var array<int, string>
     */
    private const LOCAL_HOSTS = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a URL.');

            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['host'])) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            $fail('The :attribute must start with http:// or https://.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The :attribute must not contain a username or password.');

            return;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::LOCAL_HOSTS, true) || str_ends_with($host, '.localhost')) {
            $fail('The :attribute must be a public address.');

            return;
        }

        foreach ($this->addressesFor($host) as $address) {
            if (! $this->isPublic($address)) {
                $fail('The :attribute must be a public address.');

                return;
            }
        }
    }

    /**
     * Every IP the host stands for — itself, if it's already an IP literal.
     *
     * @return array<int, string>
     */
    private function addressesFor(string $host): array
    {
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $resolved = gethostbynamel($host);

        return $resolved === false ? [] : $resolved;
    }

    /**
     * Excludes private ranges (10/8, 172.16/12, 192.168/16, fc00::/7) and
     * reserved ones (loopback, link-local, 0.0.0.0/8, and friends).
     */
    private function isPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
