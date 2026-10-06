<?php

namespace Database\Seeders;

use App\Creeping\WatchInstructions;
use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Enums\RunStatus;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Models\LandscapeAnalysis;
use App\Models\User;
use App\Models\WatchedPage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Gives a fresh install something to look at: a business with two
     * competitors, a pricing page and a changelog each read three times over
     * a fortnight — so the readings, the change log and the dashboard aren't
     * empty on first login. The readings go through the real reader, so the
     * changes are the ones it would actually have found.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $business = Business::factory()->for($user)->create([
            'name' => 'Northwind Analytics',
            'url' => null,
            'description' => 'Product analytics for small SaaS teams, competing on simplicity and a generous free tier.',
        ]);

        $user->switchBusiness($business);

        $acme = Competitor::factory()->for($business)->create([
            'name' => 'Acme Insights',
            'url' => 'https://example.com',
            'description' => 'The incumbent. Expensive, sold to enterprises, slow to ship.',
        ]);

        $widgets = Competitor::factory()->for($business)->create([
            'name' => 'Widgets Inc',
            'url' => 'https://example.org',
            'description' => 'A newer rival with a free tier like ours, and they move fast.',
        ]);

        $this->seedReadings(
            WatchedPage::factory()->for($acme)->create([
                'name' => 'Pricing',
                'url' => 'https://example.com/pricing',
                'watch_for' => 'Each plan\'s name and price, what\'s included, and any plan added or removed.',
                'category' => PageCategory::Pricing,
                'frequency' => CreepFrequency::Daily,
            ]),
            [
                14 => ['Starter' => '$49/month', 'Growth' => '$199/month', 'Enterprise' => 'Contact sales'],
                7 => ['Starter' => '$49/month', 'Growth' => '$249/month', 'Enterprise' => 'Contact sales'],
                0 => ['Starter' => '$59/month', 'Growth' => '$249/month', 'Enterprise' => 'Contact sales', 'Free trial' => '14 days'],
            ],
            'Three paid plans; Growth is the one most teams buy.',
        );

        $this->seedReadings(
            WatchedPage::factory()->for($widgets)->create([
                'name' => 'Changelog',
                'url' => 'https://example.org/changelog',
                'watch_for' => 'New releases: the version, the date, and the headline features.',
                'category' => PageCategory::Releases,
                'frequency' => CreepFrequency::Daily,
            ]),
            [
                14 => ['Latest release' => 'v2.4.0 (2 weeks ago)', 'Headline feature' => 'Funnel reports'],
                7 => ['Latest release' => 'v2.5.0 (last week)', 'Headline feature' => 'Session replay'],
                0 => ['Latest release' => 'v2.6.0 (today)', 'Headline feature' => 'AI insights', 'Free tier' => 'Now 10k events/month'],
            ],
            'A release every week or so, each leading with one big feature.',
        );

        // A comparison already run, so the dashboard opens on one.
        LandscapeAnalysis::factory()->for($business)->create();
    }

    /**
     * Read a page several times, oldest first, filing each reading and the
     * changes it revealed exactly as a real run would.
     *
     * @param  array<int, array<string, string>>  $readings  Days ago => facts (label => value).
     */
    private function seedReadings(WatchedPage $watchedPage, array $readings, string $summary): void
    {
        $instructions = new WatchInstructions;

        foreach ($readings as $daysAgo => $facts) {
            $moment = Carbon::now()->subDays($daysAgo);

            $run = CreepRun::factory()->for($watchedPage, 'watchedPage')->create([
                'status' => RunStatus::Succeeded,
                'started_at' => $moment,
                'finished_at' => $moment->copy()->addSeconds(8),
                'duration_ms' => 8000,
            ]);

            $reading = $instructions->record($run, [
                'summary' => $summary,
                'facts' => $facts,
                'captured_at' => $moment->toIso8601String(),
            ]);

            foreach ($reading->changes as $change) {
                CreepChange::create($change);
            }

            $watchedPage->unsetRelation('latestSnapshot');
        }

        $watchedPage->forceFill([
            'last_crept_at' => Carbon::now(),
            'next_creep_at' => Carbon::now()->addDay(),
        ])->save();
    }
}
