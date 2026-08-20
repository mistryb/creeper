<?php

use App\Creeping\ChangeDetector;
use App\Enums\Availability;
use App\Enums\ChangeDirection;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;

/**
 * A snapshot with every watched field pinned, so a test only varies what it
 * names. The factory randomises titles and prices, which would otherwise
 * show up as changes nobody asked about.
 */
function snapshotFor(CreepTarget $target, array $attributes = []): ProductSnapshot
{
    return ProductSnapshot::factory()->forTarget($target)->create([
        'title' => 'Kettle',
        'price_amount' => 2499,
        'currency' => 'GBP',
        'availability' => Availability::InStock,
        ...$attributes,
    ]);
}

it('finds nothing when nothing moved', function () {
    $target = CreepTarget::factory()->create();
    $attributes = ['title' => 'Kettle', 'price_amount' => 2499, 'availability' => Availability::InStock];

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, $attributes),
        snapshotFor($target, $attributes),
    );

    expect($changes)->toBe([]);
});

it('spots a price drop', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['price_amount' => 2499]),
        snapshotFor($target, ['price_amount' => 1999]),
    );

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['field'])->toBe('price')
        ->and($changes[0]['direction'])->toBe(ChangeDirection::Down);
});

it('spots a price rise', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['price_amount' => 1999]),
        snapshotFor($target, ['price_amount' => 2499]),
    );

    expect($changes[0]['direction'])->toBe(ChangeDirection::Up);
});

it('does not call an appearing price a rise', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['price_amount' => null]),
        snapshotFor($target, ['price_amount' => 2499]),
    );

    expect($changes[0]['direction'])->toBe(ChangeDirection::Changed);
});

it('spots stock coming back', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['availability' => Availability::OutOfStock]),
        snapshotFor($target, ['availability' => Availability::InStock]),
    );

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['field'])->toBe('availability')
        ->and($changes[0]['old_value'])->toBe('Out of stock')
        ->and($changes[0]['new_value'])->toBe('In stock');
});

it('spots a renamed product', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['title' => 'Kettle']),
        snapshotFor($target, ['title' => 'Kettle (2026 edition)']),
    );

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['field'])->toBe('title');
});

it('ignores rating and review churn', function () {
    $target = CreepTarget::factory()->create();
    $shared = ['title' => 'Kettle', 'price_amount' => 2499, 'availability' => Availability::InStock];

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, [...$shared, 'rating' => 4.1, 'review_count' => 10]),
        snapshotFor($target, [...$shared, 'rating' => 4.9, 'review_count' => 4000]),
    );

    expect($changes)->toBe([]);
});

it('reports several changes at once', function () {
    $target = CreepTarget::factory()->create();

    $changes = (new ChangeDetector)->compare(
        snapshotFor($target, ['title' => 'Kettle', 'price_amount' => 2499, 'availability' => Availability::InStock]),
        snapshotFor($target, ['title' => 'Kettle Pro', 'price_amount' => 1999, 'availability' => Availability::OutOfStock]),
    );

    expect(array_column($changes, 'field'))->toBe(['price', 'availability', 'title']);
});
