<?php

use App\Creeping\FactDetector;
use App\Enums\ChangeKind;
use App\Models\PageSnapshot;
use App\Models\WatchedPage;

/**
 * The changes between two readings of one page, as kind => label pairs.
 *
 * @param  array<string, string>  $before
 * @param  array<string, string>  $after
 * @return list<array<string, mixed>>
 */
function factChanges(array $before, array $after): array
{
    $watchedPage = WatchedPage::factory()->create();

    return (new FactDetector)->compare(
        PageSnapshot::factory()->of($watchedPage, $before)->create(),
        PageSnapshot::factory()->of($watchedPage, $after)->create(),
    );
}

it('reports a value that changed', function () {
    $changes = factChanges(['Pro plan' => '$20/month'], ['Pro plan' => '$25/month']);

    expect($changes)->toHaveCount(1)
        ->and($changes[0])->toMatchArray([
            'kind' => ChangeKind::Changed,
            'label' => 'Pro plan',
            'old_value' => '$20/month',
            'new_value' => '$25/month',
        ]);
});

it('reports a fact that appeared and one that went', function () {
    $changes = factChanges(['Free tier' => 'Yes'], ['Team plan' => '$99/month']);

    expect(array_map(fn (array $change): array => [$change['kind'], $change['label']], $changes))->toBe([
        [ChangeKind::Added, 'Team plan'],
        [ChangeKind::Removed, 'Free tier'],
    ]);
});

it('matches labels regardless of case, spacing and trailing punctuation', function () {
    expect(factChanges(['Pro plan' => '$20'], ['pro  plan:' => '$20']))->toBe([]);
});

it('does not count reformatting as a change', function () {
    expect(factChanges(['Pro plan' => '$20 / Month'], ['Pro plan' => '$20/month']))->toBe([]);
});

it('ties each change to the two readings it came between', function () {
    $watchedPage = WatchedPage::factory()->create();
    $before = PageSnapshot::factory()->of($watchedPage, ['Pro plan' => '$20'])->create();
    $after = PageSnapshot::factory()->of($watchedPage, ['Pro plan' => '$25'])->create();

    $change = (new FactDetector)->compare($before, $after)[0];

    expect($change['from_snapshot_id'])->toBe($before->id)
        ->and($change['to_snapshot_id'])->toBe($after->id)
        ->and($change['watched_page_id'])->toBe($watchedPage->id);
});
