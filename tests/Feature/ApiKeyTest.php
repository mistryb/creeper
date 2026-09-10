<?php

use App\Creeping\CreepManager;
use App\Enums\CreepProvider;
use App\Enums\TargetStatus;
use App\Models\ApiKey;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Support\SessionKey;

it('sends guests to log in', function () {
    $this->get(route('api-keys.index'))->assertRedirect(route('login'));
});

it('shows an empty keyring to somebody who has not added one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/api-keys')
            ->has('keys', 0)
        );
});

it('saves a key and keeps only the last four in the clear', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-keys.store'), [
            'name' => 'Personal Anthropic',
            'api_key' => 'sk-ant-api03-secret-value-abcd',
            'provider' => 'anthropic',
        ])
        ->assertSessionHasNoErrors();

    $key = $user->apiKeys()->sole();

    expect($key->name)->toBe('Personal Anthropic')
        ->and($key->provider)->toBe(CreepProvider::Anthropic)
        ->and($key->key)->toBe('sk-ant-api03-secret-value-abcd')
        ->and($key->hint)->toBe('abcd');

    // The column holds ciphertext, not the key.
    $stored = DB::table('api_keys')->where('id', $key->id)->value('key');

    expect($stored)->not->toContain('sk-ant-api03-secret-value-abcd');
});

it('keeps several keys on one keyring', function () {
    $user = User::factory()->create();

    foreach (['Work', 'Personal'] as $name) {
        $this->actingAs($user)
            ->post(route('api-keys.store'), [
                'name' => $name,
                'api_key' => 'sk-ant-api03-secret-value-'.mb_strtolower($name),
                'provider' => 'anthropic',
            ])
            ->assertSessionHasNoErrors();
    }

    expect($user->apiKeys()->pluck('name')->all())->toBe(['Work', 'Personal']);
});

it('will not take the same name twice', function () {
    $user = User::factory()->create();
    ApiKey::factory()->for($user)->create(['name' => 'Work']);

    $this->actingAs($user)
        ->post(route('api-keys.store'), [
            'name' => 'Work',
            'api_key' => 'sk-ant-api03-secret-value-abcd',
            'provider' => 'anthropic',
        ])
        ->assertSessionHasErrors('name');
});

it('lets two users name a key the same thing', function () {
    ApiKey::factory()->create(['name' => 'Work']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-keys.store'), [
            'name' => 'Work',
            'api_key' => 'sk-ant-api03-secret-value-abcd',
            'provider' => 'anthropic',
        ])
        ->assertSessionHasNoErrors();

    expect($user->apiKeys()->count())->toBe(1);
});

it('never sends a key back to the browser', function () {
    $user = User::factory()->create();
    ApiKey::factory()->for($user)->value('sk-ant-api03-secret-value-abcd')->create();

    $this->actingAs($user)
        ->get(route('api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('keys.0.hint', 'abcd'))
        ->assertDontSee('sk-ant-api03-secret-value-abcd');
});

it('rejects a key that is obviously not one', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('api-keys.store'), [
            'name' => 'Work',
            'api_key' => 'nope',
            'provider' => 'anthropic',
        ])
        ->assertSessionHasErrors('api_key');
});

it('insists on a name for the key', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('api-keys.store'), [
            'api_key' => 'sk-ant-api03-secret-value-abcd',
            'provider' => 'anthropic',
        ])
        ->assertSessionHasErrors('name');
});

it('removes a key on request', function () {
    $user = User::factory()->create();
    $key = ApiKey::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('api-keys.destroy', $key))
        ->assertSessionHasNoErrors();

    expect($user->apiKeys()->count())->toBe(0);
});

it('will not let somebody remove another user\'s key', function () {
    $key = ApiKey::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('api-keys.destroy', $key))
        ->assertForbidden();

    expect($key->fresh())->not->toBeNull();
});

it('pauses the targets a removed key was paying for', function () {
    $user = User::factory()->create();
    $key = ApiKey::factory()->for($user)->create();

    $active = CreepTarget::factory()->for($user)->for($key)->create();
    $parked = CreepTarget::factory()->failing()->for($user)->for($key)->create();
    $untouched = CreepTarget::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('api-keys.destroy', $key));

    expect($active->fresh()->status)->toBe(TargetStatus::Paused)
        ->and($active->fresh()->api_key_id)->toBeNull()
        // A parked target stays parked: reviving it is a separate decision.
        ->and($parked->fresh()->status)->toBe(TargetStatus::Failed)
        ->and($untouched->fresh()->status)->toBe(TargetStatus::Active)
        ->and($untouched->fresh()->api_key_id)->not->toBeNull();
});

it('says how many targets a removal stopped', function () {
    $user = User::factory()->create();
    $key = ApiKey::factory()->for($user)->create();
    CreepTarget::factory()->count(2)->for($user)->for($key)->create();

    $this->actingAs($user)
        ->delete(route('api-keys.destroy', $key))
        ->assertSessionHasNoErrors();

    expect(session(SessionKey::FLASH_DATA)['toast']['message'])->toContain('2 targets');
});

it('counts the targets each key is paying for', function () {
    $user = User::factory()->create();
    $key = ApiKey::factory()->for($user)->create();
    CreepTarget::factory()->count(2)->for($user)->for($key)->create();

    $this->actingAs($user)
        ->get(route('api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('keys.0.targets', 2));
});

it('hands the target\'s key to the creeping agent', function () {
    Http::preventStrayRequests();
    Http::fake(['agent.test/*' => Http::response(['title' => 'Kettle', 'price' => 24.99])]);

    config([
        'creeping.driver' => 'http',
        'creeping.drivers.http.endpoint' => 'https://agent.test/creep',
    ]);

    $key = ApiKey::factory()->value('sk-ant-api03-secret-value-abcd')->create();
    $target = CreepTarget::factory()->for($key->user)->for($key)->create();
    $run = CreepRun::factory()->running()->for($target, 'target')->create();

    app(CreepManager::class)->driver('http')->creep($run);

    Http::assertSent(fn (Request $request): bool => $request['api_key'] === 'sk-ant-api03-secret-value-abcd');
});

it('sends no key at all when the target has none', function () {
    Http::preventStrayRequests();
    Http::fake(['agent.test/*' => Http::response(['title' => 'Kettle', 'price' => 24.99])]);

    config([
        'creeping.driver' => 'http',
        'creeping.drivers.http.endpoint' => 'https://agent.test/creep',
    ]);

    $run = CreepRun::factory()->running()->for(CreepTarget::factory()->keyless(), 'target')->create();

    app(CreepManager::class)->driver('http')->creep($run);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('api_key', $request->data()));
});

it('insists on knowing which provider a key belongs to', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('api-keys.store'), [
            'name' => 'Work',
            'api_key' => 'sk-ant-api03-secret-value-abcd',
        ])
        ->assertSessionHasErrors('provider');
});

it('rejects a provider it cannot send a key to', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('api-keys.store'), [
            'name' => 'Work',
            'api_key' => 'sk-ant-api03-secret-value-abcd',
            'provider' => 'bedrock',
        ])
        ->assertSessionHasErrors('provider');
});

it('offers every supported provider on the settings screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('providers', count(CreepProvider::cases())));
});

it('names the provider a key was saved against without revealing the key', function () {
    $user = User::factory()->create();
    ApiKey::factory()->for($user)->provider(CreepProvider::OpenRouter)->create(['name' => 'Work']);

    $this->actingAs($user)
        ->get(route('api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('keys.0.name', 'Work')
            ->where('keys.0.provider', 'openrouter')
            ->where('keys.0.providerLabel', 'OpenRouter')
        );
});
