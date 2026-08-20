<?php

use App\Creeping\Data\ProductPayload;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\Availability;

it('reads a price in minor units', function () {
    expect(ProductPayload::fromArray(['title' => 'A', 'price_amount' => 1999])->priceAmount)->toBe(1999);
});

it('reads a decimal price', function () {
    expect(ProductPayload::fromArray(['title' => 'A', 'price' => 19.99])->priceAmount)->toBe(1999);
});

it('reads a price out of a display string', function (string $price, int $expected, ?string $currency) {
    $payload = ProductPayload::fromArray(['title' => 'A', 'price' => $price]);

    expect($payload->priceAmount)->toBe($expected)
        ->and($payload->currency)->toBe($currency);
})->with([
    'pounds' => ['£24.99', 2499, 'GBP'],
    'dollars' => ['$1,234.56', 123456, 'USD'],
    'euros with european separators' => ['€1.234,56', 123456, 'EUR'],
    'bare number' => ['15.00', 1500, null],
    'with noise' => ['Now only £9.99!', 999, 'GBP'],
]);

it('maps the many ways an agent describes stock', function (mixed $given, Availability $expected) {
    expect(ProductPayload::fromArray(['title' => 'A', 'availability' => $given])->availability)->toBe($expected);
})->with([
    ['in stock', Availability::InStock],
    ['InStock', Availability::InStock],
    ['out-of-stock', Availability::OutOfStock],
    ['sold out', Availability::OutOfStock],
    ['preorder', Availability::Preorder],
    ['who knows', Availability::Unknown],
    [true, Availability::InStock],
    [false, Availability::OutOfStock],
]);

it('unwraps an enveloped payload', function () {
    $payload = ProductPayload::fromArray(['product' => ['title' => 'Wrapped', 'price_amount' => 100]]);

    expect($payload->title)->toBe('Wrapped');
});

it('keeps keys it does not understand', function () {
    $payload = ProductPayload::fromArray([
        'title' => 'A',
        'price_amount' => 100,
        'shipping_estimate' => '2 days',
    ]);

    expect($payload->extra)->toBe(['shipping_estimate' => '2 days']);
});

it('accepts the alternative key names agents use', function () {
    $payload = ProductPayload::fromArray([
        'name' => 'Named not titled',
        'manufacturer' => 'Globex',
        'current_price' => 5.5,
        'reviews' => 12,
        'image' => 'https://example.com/a.jpg',
    ]);

    expect($payload->title)->toBe('Named not titled')
        ->and($payload->brand)->toBe('Globex')
        ->and($payload->priceAmount)->toBe(550)
        ->and($payload->reviewCount)->toBe(12)
        ->and($payload->imageUrl)->toBe('https://example.com/a.jpg');
});

it('refuses a payload with neither a title nor a price', function () {
    ProductPayload::fromArray(['brand' => 'Acme']);
})->throws(InvalidCreepPayload::class);

it('refuses a rating that is off the scale', function () {
    ProductPayload::fromArray(['title' => 'A', 'rating' => 11]);
})->throws(InvalidCreepPayload::class);

it('refuses an image that is not a URL', function () {
    ProductPayload::fromArray(['title' => 'A', 'image_url' => 'javascript:alert(1)']);
})->throws(InvalidCreepPayload::class);

it('falls back to now when the timestamp is nonsense', function () {
    $payload = ProductPayload::fromArray(['title' => 'A', 'captured_at' => 'not a date']);

    expect($payload->capturedAt->isToday())->toBeTrue();
});
