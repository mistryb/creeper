<?php

use App\Ai\Agents\WatchedPageAgent;
use App\Creeping\Contracts\CreepDriver;
use App\Creeping\CreepManager;
use App\Creeping\Exceptions\PageFetchFailed;
use App\Enums\CreepOutcome;
use App\Enums\CreepProvider;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\ApiKey;
use App\Models\CreepRun;
use App\Models\User;
use App\Models\WatchedPage;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'creeping.driver' => 'llm',
        'creeping.drivers.llm.model' => 'test-model',
        'creeping.drivers.llm.pin_address' => false,
    ]);
});

/**
 * The manager memoises its drivers, so any config a test needs must be in
 * place before this is called for the first time.
 */
function llmDriver(): CreepDriver
{
    return app(CreepManager::class)->driver('llm');
}

/**
 * A product page of the kind a well-behaved shop serves.
 */
function productPage(string $price = '£24.99'): string
{
    return <<<HTML
        <html><head>
            <title>Stainless Kettle — Shop</title>
            <meta property="og:title" content="Stainless Kettle">
            <script type="application/ld+json">
            {"@type":"Product","name":"Stainless Kettle","sku":"SKU-000123",
             "offers":{"@type":"Offer","price":"24.99","priceCurrency":"GBP"}}
            </script>
        </head><body>
            <h1>Stainless Kettle</h1>
            <p>{$price}</p>
            <p>In stock</p>
        </body></html>
        HTML;
}

/**
 * What the model would say about that page.
 *
 * @return array<string, mixed>
 */
function extracted(array $overrides = []): array
{
    return [
        'summary' => 'The Stainless Kettle is £24.99 and in stock.',
        'facts' => [
            ['label' => 'Price', 'value' => '£24.99'],
            ['label' => 'Stock', 'value' => 'In stock'],
        ],
        'confidence' => 'high',
        'notes' => null,
        ...$overrides,
    ];
}

it('fetches the page, reads it with the model, and returns a reading', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Succeeded)
        ->and($result->payload['summary'])->toBe('The Stainless Kettle is £24.99 and in stock.')
        ->and($result->payload['facts'][0])->toBe(['label' => 'Price', 'value' => '£24.99'])
        ->and($result->payload['source_url'])->toBe('https://shop.test/p/1');
});

it('sends the page text to the model, not the raw HTML', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    WatchedPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Stainless Kettle')
        && $prompt->contains('Structured data')
        && ! $prompt->contains('<script')
        && ! $prompt->contains('<h1>'));
});

it('tells the model what the user asked to watch for', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill([
        'url' => 'https://shop.test/p/1',
        'watch_for' => 'Only the price of the kettle, and whether it is in stock.',
    ])->save();

    llmDriver()->creep($run->fresh());

    WatchedPageAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $text = $prompt->prompt;

        return str_contains($text, '# What to watch for')
            && str_contains($text, 'Only the price of the kettle, and whether it is in stock.')
            && strpos($text, '# What to watch for') < strpos($text, '# The page');
    });
});

it('hands the model last run\'s labels so it reuses them', function () {
    Http::fake(['shop.test/*' => fn () => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted(), extracted()]);

    $watchedPage = WatchedPage::factory()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);
    WatchedPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! $prompt->contains('# Labels used last time'));

    RunCreep::dispatchSync($watchedPage->fresh());
    WatchedPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('# Labels used last time')
        && $prompt->contains('- Price')
        && $prompt->contains('- Stock'));
});

it('turns a whole run into a snapshot', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $watchedPage = WatchedPage::factory()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();
    $snapshot = $watchedPage->snapshots()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('llm')
        ->and($snapshot->summary)->toBe('The Stainless Kettle is £24.99 and in stock.')
        ->and($snapshot->facts)->toBe([
            ['label' => 'Price', 'value' => '£24.99'],
            ['label' => 'Stock', 'value' => 'In stock'],
        ]);
});

it('puts the structured data ahead of the page text, and says where they disagree', function () {
    // The JSON-LD says 24.99, the visible text says 19.99. The prompt has to
    // carry both, structured data first, so the model can prefer it.
    Http::fake(['shop.test/*' => Http::response(productPage('£19.99'), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    WatchedPageAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $text = $prompt->prompt;

        return str_contains($text, '24.99')
            && str_contains($text, '19.99')
            && strpos($text, '## Structured data') < strpos($text, '## Page text');
    });
});

it('records what the run cost against the snapshot', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $watchedPage = WatchedPage::factory()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);

    $extra = $watchedPage->snapshots()->sole()->extra;

    expect($extra)->toHaveKey('provider', 'anthropic')
        ->and($extra)->toHaveKey('model', 'test-model')
        ->and($extra)->toHaveKey('source_url', 'https://shop.test/p/1')
        ->and($extra)->toHaveKey('confidence', 'high')
        ->and($extra['usage'])->toHaveKeys(['prompt_tokens', 'completion_tokens'])
        ->and($extra['digest'])->toBeString()
        ->and($extra['redirects'])->toBe(0);
});

it('never writes the API key or the page HTML into the run payload', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->for($user)->value('sk-ant-api03-user-secret-abcd')->create();

    $watchedPage = WatchedPage::factory()->forUser($user)->for($apiKey)->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);

    $payload = (string) json_encode($watchedPage->runs()->sole()->raw_payload);

    expect($payload)->not->toContain('sk-ant-api03-user-secret-abcd')
        ->and($payload)->not->toContain('<html')
        ->and($payload)->not->toContain('<script');
});

it("spends the page's own key, and forgets it the moment it is done", function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $apiKey = ApiKey::factory()->value('sk-ant-api03-user-secret-abcd')->create();

    $during = null;

    WatchedPageAgent::fake(function (string $prompt) use ($apiKey, &$during): array {
        $during = config("ai.providers.creep_key_{$apiKey->id}");

        return extracted();
    });

    $watchedPage = WatchedPage::factory()->forUser($apiKey->user)->for($apiKey)->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $run = CreepRun::factory()->running()->for($watchedPage, 'watchedPage')->create();

    llmDriver()->creep($run);

    expect($during)->toBe(['driver' => 'anthropic', 'key' => 'sk-ant-api03-user-secret-abcd'])
        ->and(config("ai.providers.creep_key_{$apiKey->id}"))->toBeNull();
});

it('sends the key to the provider it was saved against', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $apiKey = ApiKey::factory()->provider(CreepProvider::OpenRouter)->create();

    $during = null;

    WatchedPageAgent::fake(function (string $prompt) use ($apiKey, &$during): array {
        $during = config("ai.providers.creep_key_{$apiKey->id}");

        return extracted();
    });

    $watchedPage = WatchedPage::factory()->forUser($apiKey->user)->for($apiKey)->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $run = CreepRun::factory()->running()->for($watchedPage, 'watchedPage')->create();

    llmDriver()->creep($run);

    expect($during['driver'])->toBe('openrouter');
});

it('spends whichever of a user\'s keys the page was given', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $user = User::factory()->create();
    ApiKey::factory()->for($user)->value('sk-ant-api03-the-other-key-0000')->create();
    $chosen = ApiKey::factory()->for($user)->value('sk-ant-api03-the-chosen-key-zzzz')->create();

    $during = null;

    WatchedPageAgent::fake(function (string $prompt) use ($chosen, &$during): array {
        $during = config("ai.providers.creep_key_{$chosen->id}");

        return extracted();
    });

    $watchedPage = WatchedPage::factory()->forUser($user)->for($chosen)->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep(CreepRun::factory()->running()->for($watchedPage, 'watchedPage')->create());

    expect($during['key'])->toBe('sk-ant-api03-the-chosen-key-zzzz');
});

it('refuses to run a page whose key has been removed, without fetching the page', function () {
    WatchedPageAgent::fake([extracted()]);

    $watchedPage = WatchedPage::factory()->keyless()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep(CreepRun::factory()->running()->for($watchedPage, 'watchedPage')->create());

    // No Http::fake at all, so a fetch would have been a stray request.
    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('no API key');
});

it('will not follow a redirect to a private address', function () {
    Http::fake([
        'shop.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('public address');

    WatchedPageAgent::assertNeverPrompted();
});

it('will not follow a redirect to an address dressed up as IPv6', function () {
    Http::fake([
        'shop.test/*' => Http::response('', 301, ['Location' => 'http://[::ffff:169.254.169.254]/']),
    ]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    expect(llmDriver()->creep($run->fresh())->outcome)->toBe(CreepOutcome::Failed);

    WatchedPageAgent::assertNeverPrompted();
});

it('gives up on a page whose address is no longer public', function () {
    WatchedPageAgent::fake([extracted()]);

    // Factories bypass validation, which is the same position a page is in
    // when its DNS changes under it after being saved.
    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'http://127.0.0.1/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('public address');

    Http::assertNothingSent();
    WatchedPageAgent::assertNeverPrompted();
});

it('stops after too many redirects', function () {
    Http::fake(['shop.test/*' => Http::response('', 302, ['Location' => 'https://shop.test/again'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('redirected too many times');
});

it('follows a relative redirect', function () {
    Http::fakeSequence()
        ->push('', 302, ['Location' => '/p/2'])
        ->push(productPage(), 200, ['Content-Type' => 'text/html']);

    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Succeeded)
        ->and($result->payload['source_url'])->toBe('https://shop.test/p/2')
        ->and($result->payload['redirects'])->toBe(1);
});

it('gives up on something that is not a page', function () {
    Http::fake(['shop.test/*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('not a readable page');

    WatchedPageAgent::assertNeverPrompted();
});

it('gives up on a page that is gone', function () {
    Http::fake(['shop.test/*' => Http::response('Gone', 404)]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('404');
});

it('throws when the shop is broken, so the queue tries again', function () {
    Http::fake(['shop.test/*' => Http::response('Oops', 503)]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(PageFetchFailed::class);

it('throws when the shop is blocking us, so the queue tries again later', function () {
    Http::fake(['shop.test/*' => Http::response('Blocked', 403)]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(PageFetchFailed::class);

it('refuses to read a response bigger than it agreed to', function () {
    Http::fake(['shop.test/*' => Http::response(
        '<html><body>'.str_repeat('kettle ', 500000).'</body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('larger than');
});

it('stops before paying for a page with nothing on it', function () {
    Http::fake(['shop.test/*' => Http::response('<html><body><nav>Log in</nav></body></html>', 200, ['Content-Type' => 'text/html'])]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('nothing readable');

    WatchedPageAgent::assertNeverPrompted();
});

it('bounds how much of a page it is willing to pay to read', function () {
    config(['creeping.drivers.llm.max_characters' => 800]);

    Http::fake(['shop.test/*' => Http::response(
        '<html><body><h1>Kettle</h1><p>'.str_repeat('detail ', 5000).'</p></body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);
    WatchedPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    WatchedPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => mb_strlen($prompt->prompt) < 1200);
});

it('throws when the provider is rate limiting us, so the queue backs off', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    WatchedPageAgent::fake(fn (string $prompt) => throw RateLimitedException::forProvider('anthropic', 429));

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(RateLimitedException::class);

it('gives up when the provider says the key is no good', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    WatchedPageAgent::fake(fn (string $prompt) => throw new RequestException(
        new Response(new GuzzleResponse(401, [], '{"error":"invalid x-api-key"}')),
    ));

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('rejected the key this page uses');
});

it('gives up when the provider is out of credit', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    WatchedPageAgent::fake(fn (string $prompt) => throw InsufficientCreditsException::forProvider('anthropic', 402));

    $run = CreepRun::factory()->running()->create();
    $run->watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('no credit left');
});

it('records a failed run when the model returns no summary', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    WatchedPageAgent::fake([extracted([
        'summary' => '',
        'confidence' => 'low',
        'notes' => 'This looked like a search results page.',
    ])]);

    $watchedPage = WatchedPage::factory()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('summary')
        ->and($watchedPage->snapshots()->count())->toBe(0);
});

it('keeps a reading in which nothing matched what was asked', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    WatchedPageAgent::fake([extracted([
        'summary' => 'This page does not show any pricing.',
        'facts' => [],
        'confidence' => 'low',
    ])]);

    $watchedPage = WatchedPage::factory()->create();
    $watchedPage->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($watchedPage);

    expect($watchedPage->runs()->sole()->status)->toBe(RunStatus::Succeeded)
        ->and($watchedPage->snapshots()->sole()->facts)->toBe([]);
});
