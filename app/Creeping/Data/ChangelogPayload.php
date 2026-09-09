<?php

namespace App\Creeping\Data;

use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\FeatureKind;
use App\Models\ChangelogSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Changelog data from an agent, normalised into something we can store.
 *
 * The counterpart to {@see ProductPayload}, and untrusted in exactly the same
 * way: it arrives over HTTP, its shape depends on whichever model or scraper
 * produced it, and it varies between runs. Nothing reaches
 * {@see ChangelogSnapshot} without passing through here.
 *
 * @phpstan-type Feature array{title: string, description: string|null, kind: string}
 * @phpstan-type Release array{version: string|null, released_on: string|null, title: string|null, summary: string|null, features: array<int, Feature>}
 */
final readonly class ChangelogPayload
{
    /**
     * The most releases we will keep off one page, and the most entries under
     * one release. A changelog that has been going for years arrives long;
     * only the top of it is news, and the row has to stay a sensible size.
     */
    private const MAX_RELEASES = 50;

    private const MAX_FEATURES = 25;

    /**
     * Keys we understand. Anything else is kept verbatim in `extra`, so a
     * richer agent loses nothing by talking to an older Creeper.
     *
     * @var array<int, string>
     */
    private const KNOWN_KEYS = [
        'product', 'project', 'name', 'latest_version', 'version', 'latest_released_on',
        'releases', 'entries', 'versions', 'captured_at',
    ];

    /**
     * @param  array<int, Release>  $releases
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?string $product,
        public ?string $latestVersion,
        public ?Carbon $latestReleasedOn,
        public array $releases,
        public array $extra,
        public Carbon $capturedAt,
    ) {}

    /**
     * Build a payload from whatever the agent sent.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidCreepPayload
     */
    public static function fromArray(array $data): self
    {
        $data = self::unwrap($data);

        $releases = self::releases($data);

        // An agent that found no releases either read something that is not a
        // changelog, or failed without saying so. Either way there is nothing
        // to file, and an empty reading would look like a product that had all
        // its releases withdrawn.
        if ($releases === []) {
            throw InvalidCreepPayload::because([
                'releases' => ['A changelog needs at least one release with a version, a date or a title.'],
            ]);
        }

        $normalized = [
            'product' => self::stringOrNull($data['product'] ?? $data['project'] ?? $data['name'] ?? null, 255),
            'latest_version' => self::stringOrNull($data['latest_version'] ?? $data['version'] ?? null, 64)
                ?? $releases[0]['version'],
            'latest_released_on' => self::stringOrNull($data['latest_released_on'] ?? null, 10)
                ?? self::firstDate($releases),
            'releases' => $releases,
        ];

        $validator = Validator::make($normalized, [
            'product' => ['nullable', 'string', 'max:255'],
            'latest_version' => ['nullable', 'string', 'max:64'],
            'latest_released_on' => ['nullable', 'date'],
            'releases' => ['array', 'min:1', 'max:'.self::MAX_RELEASES],
            'releases.*.version' => ['nullable', 'string', 'max:64'],
            'releases.*.released_on' => ['nullable', 'date'],
            'releases.*.title' => ['nullable', 'string', 'max:255'],
            'releases.*.summary' => ['nullable', 'string', 'max:500'],
            'releases.*.features' => ['array', 'max:'.self::MAX_FEATURES],
            'releases.*.features.*.title' => ['required', 'string', 'max:255'],
            'releases.*.features.*.description' => ['nullable', 'string', 'max:500'],
            'releases.*.features.*.kind' => ['required', 'string', Rule::in(FeatureKind::values())],
        ]);

        if ($validator->fails()) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $validator->errors()->toArray();

            throw InvalidCreepPayload::because($errors);
        }

        return new self(
            product: $normalized['product'],
            latestVersion: $normalized['latest_version'],
            latestReleasedOn: self::dateOrNull($normalized['latest_released_on']),
            releases: $releases,
            extra: self::extraKeys($data),
            capturedAt: self::extractCapturedAt($data),
        );
    }

    /**
     * Attributes ready for a {@see ChangelogSnapshot}.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotAttributes(): array
    {
        return [
            'product' => $this->product,
            'latest_version' => $this->latestVersion,
            'latest_released_on' => $this->latestReleasedOn,
            'release_count' => count($this->releases),
            'feature_count' => $this->featureCount(),
            'releases' => $this->releases,
            'extra' => $this->extra === [] ? null : $this->extra,
            'captured_at' => $this->capturedAt,
        ];
    }

    /**
     * How many things this page says shipped, across every release on it.
     */
    public function featureCount(): int
    {
        return array_sum(array_map(
            fn (array $release): int => count($release['features']),
            $this->releases,
        ));
    }

    /**
     * Agents commonly wrap the goods in an envelope.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function unwrap(array $data): array
    {
        foreach (['changelog', 'data', 'result'] as $envelope) {
            if (isset($data[$envelope]) && is_array($data[$envelope])) {
                /** @var array<string, mixed> $inner */
                $inner = $data[$envelope];

                return $inner;
            }
        }

        return $data;
    }

    /**
     * The releases on the page, normalised and in the order they were sent.
     *
     * A release nothing can identify — no version, no date, no title — is
     * dropped rather than kept, because the next run would have no way to
     * recognise it and would report it as news all over again.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, Release>
     */
    private static function releases(array $data): array
    {
        /** @var mixed $raw */
        $raw = $data['releases'] ?? $data['entries'] ?? $data['versions'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $releases = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $release = [
                'version' => self::stringOrNull($entry['version'] ?? $entry['tag'] ?? null, 64),
                'released_on' => self::isoDate($entry['released_on'] ?? $entry['date'] ?? $entry['released'] ?? null),
                'title' => self::stringOrNull($entry['title'] ?? $entry['name'] ?? $entry['heading'] ?? null, 255),
                'summary' => self::stringOrNull($entry['summary'] ?? $entry['description'] ?? null, 500),
                'features' => self::features($entry),
            ];

            if (ChangelogSnapshot::identify($release) === '') {
                continue;
            }

            $releases[] = $release;

            if (count($releases) >= self::MAX_RELEASES) {
                break;
            }
        }

        return $releases;
    }

    /**
     * What one release shipped.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, Feature>
     */
    private static function features(array $entry): array
    {
        /** @var mixed $raw */
        $raw = $entry['features'] ?? $entry['changes'] ?? $entry['items'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $features = [];

        foreach ($raw as $item) {
            // A bare list of lines is a perfectly ordinary changelog.
            $feature = is_string($item)
                ? ['title' => $item]
                : (is_array($item) ? $item : null);

            if ($feature === null) {
                continue;
            }

            /** @var array<string, mixed> $feature */
            $title = self::stringOrNull($feature['title'] ?? $feature['name'] ?? null, 255);

            if ($title === null) {
                continue;
            }

            $features[] = [
                'title' => $title,
                'description' => self::stringOrNull($feature['description'] ?? $feature['summary'] ?? null, 500),
                'kind' => FeatureKind::parse($feature['kind'] ?? $feature['type'] ?? null)->value,
            ];

            if (count($features) >= self::MAX_FEATURES) {
                break;
            }
        }

        return $features;
    }

    /**
     * @param  array<int, Release>  $releases
     */
    private static function firstDate(array $releases): ?string
    {
        foreach ($releases as $release) {
            if ($release['released_on'] !== null) {
                return $release['released_on'];
            }
        }

        return null;
    }

    /**
     * A date an agent reported, as YYYY-MM-DD, or nothing at all.
     *
     * Models are asked for an ISO date and mostly comply; "14 March 2026"
     * still parses, and anything relative is dropped rather than guessed at.
     */
    private static function isoDate(mixed $value): ?string
    {
        return self::dateOrNull(self::stringOrNull($value, 64))?->toDateString();
    }

    private static function dateOrNull(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        // Carbon happily turns "last Tuesday" into a real date, which is
        // exactly the guess the agent is told not to make. A release date has
        // a year in it or it is not a release date.
        if (preg_match('/(19|20)\d{2}/', $value) !== 1) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        // A page dated in the far future, or before software had versions, is
        // a parse that went wrong rather than a release.
        return $parsed->year < 1990 || $parsed->isAfter(Carbon::now()->addYear())
            ? null
            : $parsed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function extraKeys(array $data): array
    {
        return array_diff_key($data, array_flip(self::KNOWN_KEYS));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractCapturedAt(array $data): Carbon
    {
        $value = $data['captured_at'] ?? null;

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // A malformed timestamp shouldn't sink an otherwise good payload.
            }
        }

        return Carbon::now();
    }

    private static function stringOrNull(mixed $value, int $limit): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $limit);
    }
}
