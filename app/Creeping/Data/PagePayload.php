<?php

namespace App\Creeping\Data;

use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Models\PageSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * A reading of a watched page from a reader, normalised into something we can
 * store.
 *
 * Untrusted: it arrives from a model or over HTTP from somebody's own agent,
 * and its shape varies between runs. Nothing reaches {@see PageSnapshot}
 * without passing through here.
 */
final readonly class PagePayload
{
    /**
     * The most facts one reading may hold. Enough for a long pricing table;
     * any more and the reader is transcribing the page, not watching it.
     */
    public const MAX_FACTS = 40;

    /**
     * Keys we understand. Anything else is kept verbatim in `extra`, so a
     * richer agent loses nothing by talking to an older Creeper.
     *
     * @var list<string>
     */
    private const KNOWN_KEYS = ['summary', 'facts', 'captured_at'];

    /**
     * @param  list<array{label: string, value: string}>  $facts
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $summary,
        public array $facts,
        public array $extra,
        public Carbon $capturedAt,
    ) {}

    /**
     * Build a payload from whatever the reader sent.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidCreepPayload
     */
    public static function fromArray(array $data): self
    {
        foreach (['reading', 'data', 'result'] as $envelope) {
            if (isset($data[$envelope]) && is_array($data[$envelope])) {
                /** @var array<string, mixed> $data */
                $data = $data[$envelope];

                break;
            }
        }

        $normalized = [
            'summary' => is_string($data['summary'] ?? null) ? trim($data['summary']) : null,
            'facts' => self::facts($data['facts'] ?? null),
        ];

        $validator = Validator::make($normalized, [
            'summary' => ['required', 'string', 'max:2000'],
            'facts' => ['present', 'array', 'max:'.self::MAX_FACTS],
            'facts.*.label' => ['required', 'string', 'max:255'],
            'facts.*.value' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $validator->errors()->toArray();

            throw InvalidCreepPayload::because($errors);
        }

        return new self(
            summary: $normalized['summary'],
            facts: $normalized['facts'],
            extra: array_diff_key($data, array_flip(self::KNOWN_KEYS)),
            capturedAt: self::capturedAt($data['captured_at'] ?? null),
        );
    }

    /**
     * Attributes ready for a {@see PageSnapshot}.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotAttributes(): array
    {
        return [
            'summary' => $this->summary,
            'facts' => $this->facts,
            'extra' => $this->extra === [] ? null : $this->extra,
            'captured_at' => $this->capturedAt,
        ];
    }

    /**
     * The facts, trimmed, with blanks dropped and each label kept once.
     *
     * A label seen twice is one fact reported twice, not two facts: the first
     * wins, because the next run could not tell the two apart either.
     *
     * Accepts a list of {label, value} or a plain label => value map, since
     * both are an obvious way for an agent to write it.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function facts(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $facts = [];
        $seen = [];

        foreach ($raw as $key => $entry) {
            [$label, $value] = is_array($entry)
                ? [$entry['label'] ?? $entry['name'] ?? null, $entry['value'] ?? null]
                : [is_string($key) ? $key : null, $entry];

            $label = is_scalar($label) ? trim((string) $label) : '';
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($label === '' || $value === '') {
                continue;
            }

            $key = PageSnapshot::key($label);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $facts[] = ['label' => $label, 'value' => $value];
        }

        return $facts;
    }

    private static function capturedAt(mixed $value): Carbon
    {
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // An unreadable timestamp is not worth failing a reading over.
            }
        }

        return Carbon::now();
    }
}
