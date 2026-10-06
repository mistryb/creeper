<?php

use App\Enums\RunStatus;
use App\Models\CreepRun;
use Illuminate\Support\Facades\URL;

function callbackUrl(CreepRun $run): string
{
    return URL::temporarySignedRoute('creep.callback', now()->addHour(), ['run' => $run->id]);
}

it('completes a pending run from a signed callback', function () {
    $run = CreepRun::factory()->running()->create();

    $this->postJson(callbackUrl($run), [
        'summary' => 'Delivered late, but delivered.',
        'facts' => [['label' => 'Pro plan', 'value' => '$31.50/month']],
    ])->assertOk();

    $run->refresh();
    $snapshot = $run->snapshot;

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($snapshot->summary)->toBe('Delivered late, but delivered.')
        ->and($snapshot->facts)->toBe([['label' => 'Pro plan', 'value' => '$31.50/month']])
        ->and($snapshot->watched_page_id)->toBe($run->watched_page_id);
});

it('turns away an unsigned callback', function () {
    $run = CreepRun::factory()->running()->create();

    $this->postJson(route('creep.callback', $run), ['summary' => 'Nope', 'facts' => []])
        ->assertForbidden();

    expect($run->fresh()->status)->toBe(RunStatus::Running);
});

it('turns away a callback whose signature has expired', function () {
    $run = CreepRun::factory()->running()->create();

    $url = URL::temporarySignedRoute('creep.callback', now()->addMinute(), ['run' => $run->id]);

    $this->travel(2)->minutes();

    $this->postJson($url, ['summary' => 'Too late', 'facts' => []])->assertForbidden();
});

it('will not overwrite a run that already finished', function () {
    $run = CreepRun::factory()->create(['status' => RunStatus::Succeeded]);

    $this->postJson(callbackUrl($run), ['summary' => 'Second helping', 'facts' => []])
        ->assertStatus(409);

    expect($run->fresh()->snapshot)->toBeNull();
});

it('records a failure the agent reports', function () {
    $run = CreepRun::factory()->running()->create();

    $this->postJson(callbackUrl($run), [
        'status' => 'failed',
        'error' => 'The page was a login wall.',
    ])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('The page was a login wall.');
});

it('rejects a callback payload it cannot store', function () {
    $run = CreepRun::factory()->running()->create();

    $this->postJson(callbackUrl($run), ['brand' => 'Acme'])
        ->assertStatus(422);

    expect($run->fresh()->status)->toBe(RunStatus::Failed);
});
