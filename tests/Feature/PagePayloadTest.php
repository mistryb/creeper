<?php

use App\Creeping\Data\PagePayload;
use App\Creeping\Exceptions\InvalidCreepPayload;

it('reads a summary and a list of facts', function () {
    $payload = PagePayload::fromArray([
        'summary' => '  Pro is $20.  ',
        'facts' => [
            ['label' => ' Pro plan ', 'value' => ' $20/month '],
            ['label' => 'Free tier', 'value' => 'Yes'],
        ],
    ]);

    expect($payload->summary)->toBe('Pro is $20.')
        ->and($payload->facts)->toBe([
            ['label' => 'Pro plan', 'value' => '$20/month'],
            ['label' => 'Free tier', 'value' => 'Yes'],
        ]);
});

it('accepts facts as a plain label to value map', function () {
    $payload = PagePayload::fromArray([
        'summary' => 'Two plans.',
        'facts' => ['Pro plan' => '$20/month', 'Seats' => 5],
    ]);

    expect($payload->facts)->toBe([
        ['label' => 'Pro plan', 'value' => '$20/month'],
        ['label' => 'Seats', 'value' => '5'],
    ]);
});

it('unwraps an envelope an agent put around its reading', function () {
    $payload = PagePayload::fromArray(['result' => ['summary' => 'Wrapped.', 'facts' => []]]);

    expect($payload->summary)->toBe('Wrapped.');
});

it('drops blank facts and keeps the first of two with the same label', function () {
    $payload = PagePayload::fromArray([
        'summary' => 'Some facts.',
        'facts' => [
            ['label' => 'Pro plan', 'value' => '$20'],
            ['label' => 'pro plan:', 'value' => '$25'],
            ['label' => '', 'value' => 'orphan'],
            ['label' => 'Empty', 'value' => '  '],
            'not a fact',
        ],
    ]);

    expect($payload->facts)->toBe([['label' => 'Pro plan', 'value' => '$20']]);
});

it('allows a reading with no facts, when nothing matched', function () {
    expect(PagePayload::fromArray(['summary' => 'No pricing on this page.', 'facts' => []])->facts)->toBe([]);
});

it('refuses a reading with no summary', function () {
    PagePayload::fromArray(['facts' => [['label' => 'Pro plan', 'value' => '$20']]]);
})->throws(InvalidCreepPayload::class);

it('refuses more facts than a reading may hold', function () {
    $facts = array_map(fn (int $i): array => ['label' => "Fact {$i}", 'value' => 'x'], range(1, PagePayload::MAX_FACTS + 1));

    PagePayload::fromArray(['summary' => 'Too much.', 'facts' => $facts]);
})->throws(InvalidCreepPayload::class);

it('keeps unknown keys, and the time the agent says it read the page', function () {
    $payload = PagePayload::fromArray([
        'summary' => 'Read earlier.',
        'facts' => [],
        'confidence' => 'high',
        'captured_at' => '2026-09-01T12:00:00Z',
    ]);

    expect($payload->extra)->toBe(['confidence' => 'high'])
        ->and($payload->capturedAt->toIso8601String())->toBe('2026-09-01T12:00:00+00:00');
});
