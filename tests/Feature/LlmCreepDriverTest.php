<?php

use App\Ai\Agents\ChangelogPageAgent;
use App\Ai\Agents\ProductPageAgent;
use App\Creeping\Contracts\CreepDriver;
use App\Creeping\CreepManager;
use App\Creeping\Exceptions\PageFetchFailed;
use App\Enums\Availability;
use App\Enums\CreepOutcome;
use App\Enums\CreepProvider;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;
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
        'creeping.drivers.llm.provider' => 'anthropic',
        'creeping.drivers.llm.key' => 'sk-test-application-key-0000',
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
        'title' => 'Stainless Kettle',
        'brand' => 'Acme',
        'sku' => 'SKU-000123',
        'price' => '£24.99',
        'currency' => 'GBP',
        'availability' => 'in_stock',
        'rating' => 4.5,
        'review_count' => 812,
        'image_url' => 'https://shop.test/kettle.jpg',
        'confidence' => 'high',
        'notes' => null,
        ...$overrides,
    ];
}

it('fetches the page, reads it with the model, and returns a product', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Succeeded)
        ->and($result->payload['title'])->toBe('Stainless Kettle')
        ->and($result->payload['price'])->toBe('£24.99')
        ->and($result->payload['source_url'])->toBe('https://shop.test/p/1');
});

it('sends the page text to the model, not the raw HTML', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    ProductPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Stainless Kettle')
        && $prompt->contains('Structured data')
        && ! $prompt->contains('<script')
        && ! $prompt->contains('<h1>'));
});

it('turns a whole run into a snapshot', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $target = CreepTarget::factory()->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();
    $snapshot = $target->snapshots()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('llm')
        ->and($snapshot->title)->toBe('Stainless Kettle')
        ->and($snapshot->price_amount)->toBe(2499)
        ->and($snapshot->currency)->toBe('GBP')
        ->and($snapshot->availability)->toBe(Availability::InStock)
        ->and($snapshot->review_count)->toBe(812);
});

it('puts the structured data ahead of the page text, and says where they disagree', function () {
    // The JSON-LD says 24.99, the visible text says 19.99. The prompt has to
    // carry both, structured data first, so the model can prefer it.
    Http::fake(['shop.test/*' => Http::response(productPage('£19.99'), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    ProductPageAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $text = $prompt->prompt;

        return str_contains($text, '24.99')
            && str_contains($text, '19.99')
            && strpos($text, '## Structured data') < strpos($text, '## Page text');
    });
});

it('records what the run cost against the snapshot', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $target = CreepTarget::factory()->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($target);

    $extra = $target->snapshots()->sole()->extra;

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
    ProductPageAgent::fake([extracted()]);

    $user = User::factory()->create();
    $user->setCreepApiKey('sk-ant-api03-user-secret-abcd', CreepProvider::Anthropic);

    $target = CreepTarget::factory()->for($user)->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($target);

    $payload = (string) json_encode($target->runs()->sole()->raw_payload);

    expect($payload)->not->toContain('sk-ant-api03-user-secret-abcd')
        ->and($payload)->not->toContain('<html')
        ->and($payload)->not->toContain('<script');
});

it("spends the user's own key, and forgets it the moment it is done", function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $user = User::factory()->create();
    $user->setCreepApiKey('sk-ant-api03-user-secret-abcd', CreepProvider::Anthropic);

    $during = null;

    ProductPageAgent::fake(function (string $prompt) use ($user, &$during): array {
        $during = config("ai.providers.creep_user_{$user->id}");

        return extracted();
    });

    $target = CreepTarget::factory()->for($user)->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $run = CreepRun::factory()->running()->for($target, 'target')->create();

    llmDriver()->creep($run);

    expect($during)->toBe(['driver' => 'anthropic', 'key' => 'sk-ant-api03-user-secret-abcd'])
        ->and(config("ai.providers.creep_user_{$user->id}"))->toBeNull();
});

it('falls back to the configured key when the user has none', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $during = null;

    ProductPageAgent::fake(function (string $prompt) use (&$during): array {
        $during = config('ai.providers.creep_app');

        return extracted();
    });

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    expect($during)->toBe(['driver' => 'anthropic', 'key' => 'sk-test-application-key-0000'])
        ->and(config('ai.providers.creep_app'))->toBeNull();
});

it('sends the key to the provider the user picked, not the configured one', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $user = User::factory()->create();
    $user->setCreepApiKey('sk-or-v1-user-secret-wxyz', CreepProvider::OpenRouter);

    $during = null;

    ProductPageAgent::fake(function (string $prompt) use ($user, &$during): array {
        $during = config("ai.providers.creep_user_{$user->id}");

        return extracted();
    });

    $target = CreepTarget::factory()->for($user)->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $run = CreepRun::factory()->running()->for($target, 'target')->create();

    llmDriver()->creep($run);

    expect($during['driver'])->toBe('openrouter');
});

it('registers the configured provider when the user has no key of their own', function () {
    config(['creeping.drivers.llm.provider' => 'openrouter']);

    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    $during = null;

    ProductPageAgent::fake(function (string $prompt) use (&$during): array {
        $during = config('ai.providers.creep_app');

        return extracted();
    });

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    expect($during)->toBe(['driver' => 'openrouter', 'key' => 'sk-test-application-key-0000'])
        ->and(config('ai.providers.creep_app'))->toBeNull();
});

it('refuses to run with no key configured anywhere', function () {
    config(['creeping.drivers.llm.key' => null]);

    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(RuntimeException::class, 'No model API key is configured');

it('will not follow a redirect to a private address', function () {
    Http::fake([
        'shop.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('public address');

    ProductPageAgent::assertNeverPrompted();
});

it('will not follow a redirect to an address dressed up as IPv6', function () {
    Http::fake([
        'shop.test/*' => Http::response('', 301, ['Location' => 'http://[::ffff:169.254.169.254]/']),
    ]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    expect(llmDriver()->creep($run->fresh())->outcome)->toBe(CreepOutcome::Failed);

    ProductPageAgent::assertNeverPrompted();
});

it('gives up on a target whose address is no longer public', function () {
    ProductPageAgent::fake([extracted()]);

    // Factories bypass validation, which is the same position a target is in
    // when its DNS changes under it after being saved.
    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'http://127.0.0.1/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('public address');

    Http::assertNothingSent();
    ProductPageAgent::assertNeverPrompted();
});

it('stops after too many redirects', function () {
    Http::fake(['shop.test/*' => Http::response('', 302, ['Location' => 'https://shop.test/again'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('redirected too many times');
});

it('follows a relative redirect', function () {
    Http::fakeSequence()
        ->push('', 302, ['Location' => '/p/2'])
        ->push(productPage(), 200, ['Content-Type' => 'text/html']);

    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Succeeded)
        ->and($result->payload['source_url'])->toBe('https://shop.test/p/2')
        ->and($result->payload['redirects'])->toBe(1);
});

it('gives up on something that is not a page', function () {
    Http::fake(['shop.test/*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('not a readable page');

    ProductPageAgent::assertNeverPrompted();
});

it('gives up on a page that is gone', function () {
    Http::fake(['shop.test/*' => Http::response('Gone', 404)]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('404');
});

it('throws when the shop is broken, so the queue tries again', function () {
    Http::fake(['shop.test/*' => Http::response('Oops', 503)]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(PageFetchFailed::class);

it('throws when the shop is blocking us, so the queue tries again later', function () {
    Http::fake(['shop.test/*' => Http::response('Blocked', 403)]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(PageFetchFailed::class);

it('refuses to read a response bigger than it agreed to', function () {
    Http::fake(['shop.test/*' => Http::response(
        '<html><body>'.str_repeat('kettle ', 500000).'</body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('larger than');
});

it('stops before paying for a page with nothing on it', function () {
    Http::fake(['shop.test/*' => Http::response('<html><body><nav>Log in</nav></body></html>', 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('nothing readable');

    ProductPageAgent::assertNeverPrompted();
});

it('bounds how much of a page it is willing to pay to read', function () {
    config(['creeping.drivers.llm.max_characters' => 800]);

    Http::fake(['shop.test/*' => Http::response(
        '<html><body><h1>Kettle</h1><p>'.str_repeat('detail ', 5000).'</p></body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);
    ProductPageAgent::fake([extracted()]);

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());

    ProductPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => mb_strlen($prompt->prompt) < 1200);
});

it('throws when the provider is rate limiting us, so the queue backs off', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    ProductPageAgent::fake(fn (string $prompt) => throw RateLimitedException::forProvider('anthropic', 429));

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    llmDriver()->creep($run->fresh());
})->throws(RateLimitedException::class);

it('gives up when the provider says the key is no good', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    ProductPageAgent::fake(fn (string $prompt) => throw new RequestException(
        new Response(new GuzzleResponse(401, [], '{"error":"invalid x-api-key"}')),
    ));

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('rejected the API key');
});

it('gives up when the provider is out of credit', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    ProductPageAgent::fake(fn (string $prompt) => throw InsufficientCreditsException::forProvider('anthropic', 402));

    $run = CreepRun::factory()->running()->create();
    $run->target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    $result = llmDriver()->creep($run->fresh());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('no credit left');
});

it('records a failed run when the model finds nothing on the page', function () {
    Http::fake(['shop.test/*' => Http::response(productPage(), 200, ['Content-Type' => 'text/html'])]);

    ProductPageAgent::fake([extracted([
        'title' => null,
        'price' => null,
        'confidence' => 'low',
        'notes' => 'This looked like a search results page.',
    ])]);

    $target = CreepTarget::factory()->create();
    $target->forceFill(['url' => 'https://shop.test/p/1'])->save();

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('at least a title or a price')
        ->and($target->snapshots()->count())->toBe(0);
});

/**
 * A changelog page of the kind a well-behaved product publishes.
 */
function changelogPage(): string
{
    return <<<'HTML'
        <html><head><title>Widgets — Changelog</title></head><body>
            <nav>Docs</nav>
            <main>
                <article>
                    <header><h2>v2.4.0</h2><time datetime="2026-03-14">14 March 2026</time></header>
                    <ul><li>Bulk export for reports</li><li>Webhook retries</li></ul>
                </article>
                <article>
                    <header><h2>v2.3.0</h2></header>
                    <ul><li>Faster search</li></ul>
                </article>
            </main>
        </body></html>
        HTML;
}

/**
 * What the model would say about that page.
 *
 * @return array<string, mixed>
 */
function readChangelog(): array
{
    return [
        'product' => 'Widgets',
        'latest_version' => 'v2.4.0',
        'releases' => [
            [
                'version' => 'v2.4.0',
                'released_on' => '2026-03-14',
                'title' => null,
                'summary' => null,
                'features' => [
                    ['title' => 'Bulk export for reports', 'description' => null, 'kind' => 'feature'],
                    ['title' => 'Webhook retries', 'description' => null, 'kind' => 'improvement'],
                ],
            ],
        ],
        'confidence' => 'high',
        'notes' => null,
    ];
}

/**
 * A run against a changelog target, ready to creep.
 */
function changelogRun(): CreepRun
{
    $target = CreepTarget::factory()->changelog()->create(['url' => 'https://widgets.test/changelog']);

    return CreepRun::factory()->running()->create(['creep_target_id' => $target->id])->fresh();
}

it('reads a changelog target with the changelog agent', function () {
    Http::fake(['widgets.test/*' => Http::response(changelogPage(), 200, ['Content-Type' => 'text/html'])]);
    ProductPageAgent::fake([extracted()]);
    ChangelogPageAgent::fake([readChangelog()]);

    $result = llmDriver()->creep(changelogRun());

    expect($result->outcome)->toBe(CreepOutcome::Succeeded)
        ->and($result->payload['latest_version'])->toBe('v2.4.0')
        ->and($result->payload['releases'][0]['features'][0]['title'])->toBe('Bulk export for reports')
        ->and($result->payload['source_url'])->toBe('https://widgets.test/changelog');

    ProductPageAgent::assertNeverPrompted();
});

it('shows the changelog agent the versions and dates, and none of the furniture', function () {
    Http::fake(['widgets.test/*' => Http::response(changelogPage(), 200, ['Content-Type' => 'text/html'])]);
    ChangelogPageAgent::fake([readChangelog()]);

    llmDriver()->creep(changelogRun());

    ChangelogPageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('v2.4.0')
        && $prompt->contains('14 March 2026')
        && $prompt->contains('Webhook retries')
        && ! $prompt->contains('Docs')
        && ! $prompt->contains('<article'));
});

it('turns a whole changelog run into a snapshot', function () {
    Http::fake(['widgets.test/*' => Http::response(changelogPage(), 200, ['Content-Type' => 'text/html'])]);
    ChangelogPageAgent::fake([readChangelog()]);

    $target = CreepTarget::factory()->changelog()->create(['url' => 'https://widgets.test/changelog']);

    RunCreep::dispatchSync($target);

    $snapshot = $target->changelogSnapshots()->sole();

    expect($target->runs()->sole()->status)->toBe(RunStatus::Succeeded)
        ->and($snapshot->product)->toBe('Widgets')
        ->and($snapshot->latest_version)->toBe('v2.4.0')
        ->and($snapshot->feature_count)->toBe(2)
        ->and($snapshot->extra['confidence'])->toBe('high');
});

it('tells a changelog watcher what a thin page means for them', function () {
    Http::fake(['widgets.test/*' => Http::response('<html><body><nav>Log in</nav></body></html>', 200, ['Content-Type' => 'text/html'])]);
    ChangelogPageAgent::fake([readChangelog()]);

    $result = llmDriver()->creep(changelogRun());

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('Release notes');

    ChangelogPageAgent::assertNeverPrompted();
});
