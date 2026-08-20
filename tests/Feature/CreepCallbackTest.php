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
        'title' => 'Delivered late, but delivered',
        'price' => 31.5,
        'currency' => 'gbp',
        'availability' => 'in_stock',
    ])->assertOk();

    $run->refresh();
    $snapshot = $run->snapshot;

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($snapshot->title)->toBe('Delivered late, but delivered')
        ->and($snapshot->price_amount)->toBe(3150)
        ->and($snapshot->currency)->toBe('GBP')
        ->and($snapshot->creep_target_id)->toBe($run->creep_target_id);
});

it('turns away an unsigned callback', function () {
    $run = CreepRun::factory()->running()->create();

    $this->postJson(route('creep.callback', $run), ['title' => 'Nope', 'price' => 1])
        ->assertForbidden();

    expect($run->fresh()->status)->toBe(RunStatus::Running);
});

it('turns away a callback whose signature has expired', function () {
    $run = CreepRun::factory()->running()->create();

    $url = URL::temporarySignedRoute('creep.callback', now()->addMinute(), ['run' => $run->id]);

    $this->travel(2)->minutes();

    $this->postJson($url, ['title' => 'Too late', 'price' => 1])->assertForbidden();
});

it('will not overwrite a run that already finished', function () {
    $run = CreepRun::factory()->create(['status' => RunStatus::Succeeded]);

    $this->postJson(callbackUrl($run), ['title' => 'Second helping', 'price' => 5])
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
