<?php

use App\Creeping\Data\ChangelogPayload;
use App\Creeping\Exceptions\InvalidCreepPayload;
use Illuminate\Support\Carbon;

it('normalises what an agent reports about a changelog', function () {
    $payload = ChangelogPayload::fromArray([
        'product' => 'Widgets',
        'releases' => [
            [
                'version' => 'v2.1.0',
                'released_on' => '14 March 2026',
                'title' => 'Spring release',
                'summary' => 'Exports, mostly.',
                'features' => [
                    ['title' => 'Bulk export', 'description' => 'CSV and JSON.', 'kind' => 'added'],
                    ['title' => 'Legacy API removed', 'description' => null, 'kind' => 'breaking change'],
                ],
            ],
        ],
        'confidence' => 'high',
    ]);

    expect($payload->product)->toBe('Widgets')
        ->and($payload->latestVersion)->toBe('v2.1.0')
        ->and($payload->latestReleasedOn?->toDateString())->toBe('2026-03-14')
        ->and($payload->releases[0]['released_on'])->toBe('2026-03-14')
        ->and($payload->releases[0]['title'])->toBe('Spring release')
        // The kinds an agent invents are mapped onto the ones we know.
        ->and($payload->releases[0]['features'][0]['kind'])->toBe('feature')
        ->and($payload->releases[0]['features'][1]['kind'])->toBe('breaking')
        ->and($payload->featureCount())->toBe(2);
});

it('takes the newest version off the top of the list when the agent did not name one', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [
            ['version' => 'v3.0.0', 'features' => []],
            ['version' => 'v2.9.0', 'features' => []],
        ],
    ]);

    expect($payload->latestVersion)->toBe('v3.0.0');
});

it('reads a changelog out of an envelope', function () {
    $payload = ChangelogPayload::fromArray([
        'changelog' => ['releases' => [['version' => 'v1.0.0', 'features' => []]]],
    ]);

    expect($payload->latestVersion)->toBe('v1.0.0');
});

it('accepts the other names agents give these things', function () {
    $payload = ChangelogPayload::fromArray([
        'project' => 'Widgets',
        'entries' => [
            [
                'tag' => 'v1.2.3',
                'date' => '2026-01-02',
                'heading' => 'January',
                'changes' => [
                    ['name' => 'Faster search', 'type' => 'performance'],
                    'A bare line, as changelogs are often written',
                ],
            ],
        ],
    ]);

    expect($payload->product)->toBe('Widgets')
        ->and($payload->latestVersion)->toBe('v1.2.3')
        ->and($payload->releases[0]['title'])->toBe('January')
        ->and($payload->releases[0]['features'][0]['kind'])->toBe('improvement')
        ->and($payload->releases[0]['features'][1]['title'])->toBe('A bare line, as changelogs are often written')
        ->and($payload->releases[0]['features'][1]['kind'])->toBe('other');
});

it('identifies a release by its date when it has no version', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [['released_on' => '2026-02-01', 'title' => 'February update', 'features' => []]],
    ]);

    expect($payload->latestVersion)->toBeNull()
        ->and($payload->latestReleasedOn?->toDateString())->toBe('2026-02-01')
        ->and($payload->releases)->toHaveCount(1);
});

it('drops a release nothing could recognise again', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [
            ['version' => 'v1.0.0', 'features' => []],
            ['version' => null, 'released_on' => null, 'title' => null, 'features' => [['title' => 'Orphan']]],
        ],
    ]);

    expect($payload->releases)->toHaveCount(1);
});

it('drops a date it cannot make sense of rather than guessing at one', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [['version' => 'v1.0.0', 'released_on' => 'last Tuesday', 'features' => []]],
    ]);

    expect($payload->releases[0]['released_on'])->toBeNull()
        ->and($payload->latestReleasedOn)->toBeNull();
});

it('refuses a date from the far future', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [['version' => 'v1.0.0', 'released_on' => Carbon::now()->addYears(5)->toDateString(), 'features' => []]],
    ]);

    expect($payload->releases[0]['released_on'])->toBeNull();
});

it('keeps what it does not understand', function () {
    $payload = ChangelogPayload::fromArray([
        'releases' => [['version' => 'v1.0.0', 'features' => []]],
        'confidence' => 'low',
        'notes' => 'This looked like a blog index.',
    ]);

    expect($payload->extra)->toBe([
        'confidence' => 'low',
        'notes' => 'This looked like a blog index.',
    ]);
});

it('bounds how much of a long changelog it will store', function () {
    $releases = [];

    foreach (range(1, 80) as $minor) {
        $releases[] = ['version' => "v1.{$minor}.0", 'features' => []];
    }

    $payload = ChangelogPayload::fromArray(['releases' => $releases]);

    expect($payload->releases)->toHaveCount(50);
});

it('refuses a payload with no releases in it', function () {
    ChangelogPayload::fromArray(['product' => 'Widgets', 'releases' => []]);
})->throws(InvalidCreepPayload::class, 'at least one release');

it('refuses a payload that is not a changelog at all', function () {
    ChangelogPayload::fromArray(['title' => 'Stainless Kettle', 'price' => '£24.99']);
})->throws(InvalidCreepPayload::class);

it('turns itself into snapshot attributes', function () {
    $attributes = ChangelogPayload::fromArray([
        'product' => 'Widgets',
        'releases' => [['version' => 'v1.0.0', 'released_on' => '2026-01-01', 'features' => [['title' => 'Bulk export']]]],
    ])->toSnapshotAttributes();

    expect($attributes['product'])->toBe('Widgets')
        ->and($attributes['latest_version'])->toBe('v1.0.0')
        ->and($attributes['release_count'])->toBe(1)
        ->and($attributes['feature_count'])->toBe(1)
        ->and($attributes['releases'][0]['features'][0]['kind'])->toBe('other');
});
