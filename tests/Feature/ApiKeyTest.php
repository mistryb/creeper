<?php

use App\Creeping\CreepManager;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('sends guests to log in', function () {
    $this->get(route('api-key.edit'))->assertRedirect(route('login'));
});

it('shows an empty key screen to somebody who has not added one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('api-key.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/api-key')
            ->where('hasKey', false)
            ->where('hint', null)
        );
});

it('saves a key and keeps only the last four in the clear', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('api-key.update'), ['api_key' => 'sk-ant-api03-secret-value-abcd'])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->hasCreepApiKey())->toBeTrue()
        ->and($user->creep_api_key)->toBe('sk-ant-api03-secret-value-abcd')
        ->and($user->creep_api_key_hint)->toBe('abcd');

    // The column holds ciphertext, not the key.
    $stored = DB::table('users')->where('id', $user->id)->value('creep_api_key');

    expect($stored)->not->toContain('sk-ant-api03-secret-value-abcd');
});

it('never sends the key back to the browser', function () {
    $user = User::factory()->create();
    $user->setCreepApiKey('sk-ant-api03-secret-value-abcd');

    $this->actingAs($user)
        ->get(route('api-key.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hint', 'abcd'))
        ->assertDontSee('sk-ant-api03-secret-value-abcd');
});

it('rejects a key that is obviously not one', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('api-key.update'), ['api_key' => 'nope'])
        ->assertSessionHasErrors('api_key');
});

it('removes a key on request', function () {
    $user = User::factory()->create();
    $user->setCreepApiKey('sk-ant-api03-secret-value-abcd');

    $this->actingAs($user)
        ->delete(route('api-key.destroy'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->hasCreepApiKey())->toBeFalse()
        ->and($user->creep_api_key_hint)->toBeNull();
});

it('hands the key to the creeping agent', function () {
    Http::preventStrayRequests();
    Http::fake(['agent.test/*' => Http::response(['title' => 'Kettle', 'price' => 24.99])]);

    config([
        'creeping.driver' => 'http',
        'creeping.drivers.http.endpoint' => 'https://agent.test/creep',
    ]);

    $user = User::factory()->create();
    $user->setCreepApiKey('sk-ant-api03-secret-value-abcd');

    $target = CreepTarget::factory()->for($user)->create();
    $run = CreepRun::factory()->running()->for($target, 'target')->create();

    app(CreepManager::class)->driver('http')->creep($run);

    Http::assertSent(fn (Request $request): bool => $request['api_key'] === 'sk-ant-api03-secret-value-abcd');
});

it('sends no key at all when the user has not set one', function () {
    Http::preventStrayRequests();
    Http::fake(['agent.test/*' => Http::response(['title' => 'Kettle', 'price' => 24.99])]);

    config([
        'creeping.driver' => 'http',
        'creeping.drivers.http.endpoint' => 'https://agent.test/creep',
    ]);

    $run = CreepRun::factory()->running()->create();

    app(CreepManager::class)->driver('http')->creep($run);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('api_key', $request->data()));
});

it('will not creep for a subscriber who has no key on file', function () {
    $user = User::factory()->create();
    subscribe($user);

    $target = CreepTarget::factory()->for($user)->create();

    RunCreep::dispatchSync($target);

    expect($target->runs()->sole()->error)->toContain('No model API key');
});
