<?php

use App\Actions\BuildDigest;
use App\Ai\Agents\DigestAgent;
use App\Enums\DigestFrequency;
use App\Enums\RunStatus;
use App\Jobs\RunDigest;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\Digest;
use App\Models\User;
use App\Notifications\DigestReady;
use Database\Factories\DigestFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A company with a key, two competitors, and a digest queued about them.
 *
 * @return array{0: Digest, 1: Competitor, 2: Competitor}
 */
function queuedDigest(array $business = []): array
{
    $business = Business::factory()->withKey()->create([
        'digest_frequency' => DigestFrequency::Weekly,
        'last_digest_at' => Carbon::now()->subWeek(),
        ...$business,
    ]);

    $acme = Competitor::factory()->for($business)->create(['name' => 'Acme Insights']);
    $widgets = Competitor::factory()->for($business)->create(['name' => 'Widgets Inc']);

    return [Digest::queueFor($business), $acme, $widgets];
}

describe('writing a digest', function () {
    it('stores the report, emails it, and schedules the next one', function () {
        Notification::fake();
        Carbon::setTestNow('2026-10-14 10:00:00');

        [$digest, $acme, $widgets] = queuedDigest();

        DigestAgent::fake([DigestFactory::report([$acme, $widgets])]);

        app(BuildDigest::class)->handle($digest);

        $digest->refresh();
        $business = $digest->business->fresh();

        expect($digest->status)->toBe(RunStatus::Succeeded)
            ->and($digest->report['headline'])->toBe('Prices went up across the board this week.')
            ->and($digest->report['competitors'])->toHaveCount(2)
            ->and($digest->report['competitors'][0]['subject'])->toBe($acme->subject())
            ->and($digest->report['competitors'][0]['items'][0]['url'])->toBe('https://example.com/pricing')
            ->and($digest->sent_at)->not->toBeNull()
            ->and($digest->provider)->toBe('anthropic')
            ->and($business->last_digest_at->toDateTimeString())->toBe('2026-10-14 10:00:00')
            // The next Monday morning after today (a Wednesday).
            ->and($business->next_digest_at->toDateTimeString())->toBe('2026-10-19 08:00:00');

        Notification::assertSentTo($business->user, DigestReady::class, fn (DigestReady $notification): bool => $notification->digest->is($digest));
    });

    it('covers the period since the last digest, naming every competitor by subject id', function () {
        Notification::fake();

        [$digest, $acme, $widgets] = queuedDigest(['description' => 'Product analytics for small teams.']);
        $acme->update(['reason' => 'The incumbent.']);

        DigestAgent::fake([DigestFactory::report([$acme, $widgets])]);

        app(BuildDigest::class)->handle($digest);

        DigestAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Product analytics for small teams.')
            && $prompt->contains("Acme Insights (subject: {$acme->subject()})")
            && $prompt->contains('Why they compete: The incumbent.')
            && $prompt->contains("Widgets Inc (subject: {$widgets->subject()})")
            && $prompt->contains($digest->period_start->toFormattedDateString()));
    });

    it('lines the report up with the competitors: one section each, strangers dropped, quiet ones filled in', function () {
        Notification::fake();

        [$digest, $acme, $widgets] = queuedDigest();

        DigestAgent::fake([[
            'headline' => 'Only Widgets moved.',
            'competitors' => [
                ['subject' => 'competitor:999', 'summary' => 'Not asked about.', 'items' => []],
                ['subject' => $widgets->subject(), 'summary' => 'Shipped a thing.', 'items' => [
                    ['title' => 'A thing', 'detail' => 'Shipped.', 'url' => 'http://localhost/private', 'date' => '2026-10-10'],
                    ['title' => '', 'detail' => 'No title, so dropped.', 'url' => null, 'date' => null],
                ]],
            ],
            'notes' => null,
        ]]);

        app(BuildDigest::class)->handle($digest);

        $sections = $digest->fresh()->report['competitors'];

        expect($sections)->toHaveCount(2)
            ->and($sections[0]['subject'])->toBe($acme->subject())
            ->and($sections[0]['summary'])->toContain('Nothing new')
            ->and($sections[0]['items'])->toBe([])
            ->and($sections[1]['subject'])->toBe($widgets->subject())
            ->and($sections[1]['items'])->toHaveCount(1)
            // A private address is not a source anyone can follow.
            ->and($sections[1]['items'][0]['url'])->toBeNull();
    });

    it('spends the company\'s key, and only for the length of the call', function () {
        Notification::fake();

        $apiKey = ApiKey::factory()->value('sk-ant-api03-user-secret-abcd')->create();
        $business = Business::factory()->for($apiKey->user)->create(['api_key_id' => $apiKey->id]);
        $competitor = Competitor::factory()->for($business)->create();

        $during = null;

        DigestAgent::fake(function (string $prompt) use ($apiKey, $competitor, &$during): array {
            $during = config("ai.providers.creep_key_{$apiKey->id}");

            return DigestFactory::report([$competitor]);
        });

        app(BuildDigest::class)->handle(Digest::queueFor($business));

        expect($during)->toBe(['driver' => 'anthropic', 'key' => 'sk-ant-api03-user-secret-abcd'])
            ->and(config("ai.providers.creep_key_{$apiKey->id}"))->toBeNull();
    });

    it('fails without sending when the key has been removed, and still moves the schedule on', function () {
        Notification::fake();
        Carbon::setTestNow('2026-10-14 10:00:00');

        [$digest] = queuedDigest();
        $digest->apiKey->delete();

        DigestAgent::fake([DigestFactory::report()]);

        app(BuildDigest::class)->handle($digest->fresh());

        $digest->refresh();

        expect($digest->status)->toBe(RunStatus::Failed)
            ->and($digest->error)->toContain('removed')
            ->and($digest->sent_at)->toBeNull()
            ->and($digest->business->fresh()->next_digest_at->toDateTimeString())->toBe('2026-10-19 08:00:00');

        Notification::assertNothingSent();
        DigestAgent::assertNeverPrompted();
    });

    it('fails when there is nobody to write about', function () {
        Notification::fake();

        $business = Business::factory()->withKey()->create();

        DigestAgent::fake([DigestFactory::report()]);

        $digest = Digest::queueFor($business);

        app(BuildDigest::class)->handle($digest);

        expect($digest->fresh()->status)->toBe(RunStatus::Failed)
            ->and($digest->fresh()->error)->toContain('nobody');

        Notification::assertNothingSent();
    });

    it('turns a provider refusal into a sentence', function () {
        Notification::fake();

        [$digest] = queuedDigest();

        DigestAgent::fake(fn (string $prompt) => throw new InsufficientCreditsException('no credit'));

        app(BuildDigest::class)->handle($digest);

        expect($digest->fresh()->status)->toBe(RunStatus::Failed)
            ->and($digest->fresh()->error)->toContain('no credit left');

        Notification::assertNothingSent();
    });

    it('fails when the model answers without a headline', function () {
        Notification::fake();

        [$digest] = queuedDigest();

        DigestAgent::fake([['competitors' => []]]);

        app(BuildDigest::class)->handle($digest);

        expect($digest->fresh()->status)->toBe(RunStatus::Failed);

        Notification::assertNothingSent();
    });

    it('marks a run that died mid-way as failed', function () {
        [$digest] = queuedDigest();
        $digest->markRunning();

        (new RunDigest($digest))->failed(new RuntimeException('worker died'));

        expect($digest->fresh()->status)->toBe(RunStatus::Failed);
    });
});

describe('the email', function () {
    it('carries the headline and every competitor\'s news, with a link to the digest', function () {
        $business = Business::factory()->create();
        $acme = Competitor::factory()->for($business)->create(['name' => 'Acme Insights']);
        $gone = Competitor::factory()->for($business)->create(['name' => 'Gone Already']);

        $digest = Digest::factory()->for($business)->create(['report' => DigestFactory::report([$acme, $gone])]);
        $gone->delete();

        $rendered = (new DigestReady($digest))->toMail($business->user)->render()->toHtml();

        expect($rendered)->toContain('Prices went up across the board this week.')
            ->toContain('Acme Insights')
            ->toContain('Growth plan now $249/month')
            ->toContain('https://example.com/pricing')
            ->toContain(route('digests.show', $digest))
            ->not->toContain('Gone Already');
    });
});

describe('the screens', function () {
    it('lists a company\'s digests, newest first', function () {
        $business = Business::factory()->create();
        $older = Digest::factory()->for($business)->create();
        $newer = Digest::factory()->for($business)->failed()->create();
        Digest::factory()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.digests.index', $business))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('digests/index')
                ->where('business.data.id', $business->id)
                ->has('digests.data', 2)
                ->where('digests.data.0.id', $newer->id)
                ->where('digests.data.1.id', $older->id)
            );
    });

    it('gives the digest list what it needs to name competitors and show a run in progress', function () {
        $business = Business::factory()->withKey()->create();
        $competitor = Competitor::factory()->for($business)->create();
        Digest::factory()->for($business)->create(['report' => DigestFactory::report([$competitor])]);
        Digest::factory()->for($business)->queued()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.digests.index', $business))
            ->assertInertia(fn ($page) => $page
                ->component('digests/index')
                ->where('business.data.api_key_label', $business->apiKey->label())
                ->has('competitors.data', 1)
                ->where('competitors.data.0.subject', $competitor->subject())
                ->where('isDigesting', true)
                ->where('hasApiKeys', true)
            );
    });

    it('shows one digest with the competitors it names', function () {
        $business = Business::factory()->create();
        $competitor = Competitor::factory()->for($business)->create();
        $digest = Digest::factory()->for($business)->create(['report' => DigestFactory::report([$competitor])]);

        $this->actingAs($business->user)
            ->get(route('digests.show', $digest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('digests/show')
                ->where('digest.data.id', $digest->id)
                ->where('digest.data.report.headline', 'Prices went up across the board this week.')
                ->has('competitors.data', 1)
                ->where('competitors.data.0.subject', $competitor->subject())
            );
    });

    it('keeps other people out', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create();
        $digest = Digest::factory()->for($business)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('businesses.digests.index', $business))->assertForbidden();
        $this->actingAs($stranger)->get(route('digests.show', $digest))->assertForbidden();
        $this->actingAs($stranger)->post(route('businesses.digests.store', $business))->assertForbidden();

        Queue::assertNothingPushed();
    });
});

describe('sending now', function () {
    it('queues a digest covering everything since the last one', function () {
        Queue::fake();
        Carbon::setTestNow('2026-10-14 10:00:00');

        $business = Business::factory()->withKey()->create(['last_digest_at' => Carbon::parse('2026-10-07 08:00:00')]);

        $this->actingAs($business->user)
            ->post(route('businesses.digests.store', $business))
            ->assertRedirect();

        $digest = $business->digests()->sole();

        expect($digest->status)->toBe(RunStatus::Queued)
            ->and($digest->api_key_id)->toBe($business->api_key_id)
            ->and($digest->period_start->toDateTimeString())->toBe('2026-10-07 08:00:00')
            ->and($digest->period_end->toDateTimeString())->toBe('2026-10-14 10:00:00');

        Queue::assertPushed(RunDigest::class, fn (RunDigest $job): bool => $job->digest->is($digest));
    });

    it('starts from the day the company was set up when none has gone out', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create(['last_digest_at' => null]);

        $this->actingAs($business->user)->post(route('businesses.digests.store', $business));

        expect($business->digests()->sole()->period_start->toDateTimeString())
            ->toBe($business->created_at->toDateTimeString());
    });

    it('refuses while one is already being written, and without a key', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create();
        Digest::factory()->for($business)->running()->create();

        $this->actingAs($business->user)->post(route('businesses.digests.store', $business));

        expect($business->digests()->count())->toBe(1);

        $keyless = Business::factory()->for($business->user)->create();

        $this->actingAs($business->user)->post(route('businesses.digests.store', $keyless));

        expect($keyless->digests()->count())->toBe(0);

        Queue::assertNothingPushed();
    });
});
