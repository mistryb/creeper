<?php

namespace App\Creeping\Data;

use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\Availability;
use App\Models\ProductSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Product data from an agent, normalised into something we can store.
 *
 * Agent output is untrusted input: it arrives over HTTP, it's shaped by
 * whatever model or scraper produced it, and it varies between runs. Every
 * payload passes through here before it becomes a snapshot.
 */
final readonly class ProductPayload
{
    /**
     * Keys we understand. Anything else is kept verbatim in `extra`, so a
     * richer agent loses nothing by talking to an older Creeper.
     *
     * @var array<int, string>
     */
    private const KNOWN_KEYS = [
        'title', 'name', 'brand', 'manufacturer', 'sku', 'price', 'price_amount',
        'current_price', 'currency', 'availability', 'in_stock', 'stock_status',
        'rating', 'review_count', 'reviews', 'image_url', 'image', 'captured_at',
    ];

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?string $title,
        public ?string $brand,
        public ?string $sku,
        public ?int $priceAmount,
        public ?string $currency,
        public Availability $availability,
        public ?float $rating,
        public ?int $reviewCount,
        public ?string $imageUrl,
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

        $normalized = [
            'title' => self::stringOrNull($data['title'] ?? $data['name'] ?? null),
            'brand' => self::stringOrNull($data['brand'] ?? $data['manufacturer'] ?? null),
            'sku' => self::stringOrNull($data['sku'] ?? null),
            'price_amount' => self::extractPriceAmount($data),
            'currency' => self::extractCurrency($data),
            'rating' => self::floatOrNull($data['rating'] ?? null),
            'review_count' => self::intOrNull($data['review_count'] ?? $data['reviews'] ?? null),
            'image_url' => self::stringOrNull($data['image_url'] ?? $data['image'] ?? null),
        ];

        $validator = Validator::make($normalized, [
            'title' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        if ($validator->fails()) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $validator->errors()->toArray();

            throw InvalidCreepPayload::because($errors);
        }

        // A payload with nothing recognisable in it means the agent failed
        // without saying so. Better to fail the run than store an empty card.
        if ($normalized['title'] === null && $normalized['price_amount'] === null) {
            throw InvalidCreepPayload::because([
                'payload' => ['A product needs at least a title or a price.'],
            ]);
        }

        return new self(
            title: $normalized['title'],
            brand: $normalized['brand'],
            sku: $normalized['sku'],
            priceAmount: $normalized['price_amount'],
            currency: $normalized['currency'],
            availability: Availability::parse($data['availability'] ?? $data['stock_status'] ?? $data['in_stock'] ?? null),
            rating: $normalized['rating'],
            reviewCount: $normalized['review_count'],
            imageUrl: $normalized['image_url'],
            extra: self::extraKeys($data),
            capturedAt: self::extractCapturedAt($data),
        );
    }

    /**
     * Attributes ready for a {@see ProductSnapshot}.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotAttributes(): array
    {
        return [
            'title' => $this->title,
            'brand' => $this->brand,
            'sku' => $this->sku,
            'price_amount' => $this->priceAmount,
            'currency' => $this->currency,
            'availability' => $this->availability,
            'rating' => $this->rating,
            'review_count' => $this->reviewCount,
            'image_url' => $this->imageUrl,
            'extra' => $this->extra === [] ? null : $this->extra,
            'captured_at' => $this->capturedAt,
        ];
    }

    /**
     * Agents commonly wrap the goods in a `product` or `data` envelope.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function unwrap(array $data): array
    {
        foreach (['product', 'data', 'result'] as $envelope) {
            if (isset($data[$envelope]) && is_array($data[$envelope])) {
                /** @var array<string, mixed> $inner */
                $inner = $data[$envelope];

                return $inner;
            }
        }

        return $data;
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
     * The price in minor units, whether the agent sent minor units, a number,
     * or a display string like "£1,234.56".
     *
     * @param  array<string, mixed>  $data
     */
    private static function extractPriceAmount(array $data): ?int
    {
        if (isset($data['price_amount']) && is_numeric($data['price_amount'])) {
            return (int) $data['price_amount'];
        }

        $raw = $data['price'] ?? $data['current_price'] ?? null;

        if (is_int($raw) || is_float($raw)) {
            return (int) round($raw * 100);
        }

        if (! is_string($raw)) {
            return null;
        }

        $decimal = self::parseDecimal($raw);

        return $decimal === null ? null : (int) round($decimal * 100);
    }

    /**
     * Pull a number out of a display string, coping with both "1,234.56"
     * and "1.234,56". The last separator seen is the decimal point.
     */
    private static function parseDecimal(string $value): ?float
    {
        $digits = preg_replace('/[^0-9.,]/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        $lastComma = strrpos($digits, ',');
        $lastDot = strrpos($digits, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $digits = str_replace('.', '', $digits);
            $digits = str_replace(',', '.', $digits);
        } else {
            $digits = str_replace(',', '', $digits);
        }

        return is_numeric($digits) ? (float) $digits : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractCurrency(array $data): ?string
    {
        $currency = $data['currency'] ?? null;

        if (is_string($currency) && strlen(trim($currency)) === 3) {
            return mb_strtoupper(trim($currency));
        }

        $price = $data['price'] ?? $data['current_price'] ?? null;

        if (! is_string($price)) {
            return null;
        }

        foreach (['£' => 'GBP', '$' => 'USD', '€' => 'EUR', '¥' => 'JPY'] as $symbol => $code) {
            if (str_contains($price, $symbol)) {
                return $code;
            }
        }

        return null;
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

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
