<?php

namespace Database\Seeders;

use App\Creeping\ReleaseDetector;
use App\Enums\Availability;
use App\Enums\ChangeDirection;
use App\Enums\CreepFrequency;
use App\Enums\CreepType;
use App\Models\ChangelogSnapshot;
use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Gives a fresh install something to look at: three product targets with a
     * few weeks of price history behind them, and a changelog target that has
     * shipped twice since Creeper started watching it — so the chart, the
     * releases and the change log aren't empty on first login.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $products = [
            ['name' => 'Stainless kettle', 'url' => 'https://example.com/products/stainless-kettle', 'price' => 4999, 'brand' => 'Acme'],
            ['name' => 'Mechanical keyboard', 'url' => 'https://example.com/products/mechanical-keyboard', 'price' => 12900, 'brand' => 'Globex'],
            ['name' => 'Running shoes', 'url' => 'https://example.com/products/running-shoes', 'price' => 8500, 'brand' => 'Initech'],
        ];

        foreach ($products as $product) {
            $target = CreepTarget::factory()->for($user)->create([
                'name' => $product['name'],
                'url' => $product['url'],
                'frequency' => CreepFrequency::Daily,
                'last_crept_at' => Carbon::now(),
                'next_creep_at' => Carbon::now()->addDay(),
            ]);

            $this->seedHistory($target, $product['price'], $product['brand'], $product['name']);
        }

        $this->seedChangelog($user);
    }

    /**
     * A changelog read three times, a release longer each time.
     */
    private function seedChangelog(User $user): void
    {
        $target = CreepTarget::factory()->for($user)->create([
            'type' => CreepType::Changelog,
            'name' => 'Widgets changelog',
            'url' => 'https://example.com/changelog',
            'frequency' => CreepFrequency::Daily,
            'last_crept_at' => Carbon::now(),
            'next_creep_at' => Carbon::now()->addDay(),
        ]);

        $detector = new ReleaseDetector;
        $previous = null;

        foreach ([14, 7, 0] as $daysAgo) {
            $capturedAt = Carbon::now()->subDays($daysAgo);

            $run = CreepRun::factory()->create([
                'creep_target_id' => $target->id,
                'started_at' => $capturedAt,
                'finished_at' => $capturedAt->copy()->addSeconds(9),
            ]);

            /** @var ChangelogSnapshot $snapshot */
            $snapshot = ChangelogSnapshot::factory()
                ->listing($this->releasesPublishedBy($capturedAt))
                ->create([
                    'creep_run_id' => $run->id,
                    'creep_target_id' => $target->id,
                    'product' => 'Widgets',
                    'captured_at' => $capturedAt,
                ]);

            if ($previous instanceof ChangelogSnapshot) {
                foreach ($detector->compare($previous, $snapshot) as $attributes) {
                    CreepChange::create($attributes);
                }
            }

            $previous = $snapshot;
        }
    }

    /**
     * The releases a changelog page would have been listing on a given day,
     * newest first, the way the page reads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function releasesPublishedBy(Carbon $moment): array
    {
        $catalogue = [
            ['version' => 'v2.2.0', 'weeks' => 4, 'features' => [
                ['title' => 'Saved views', 'description' => 'Keep a filter you use every day.', 'kind' => 'feature'],
                ['title' => 'Faster exports', 'description' => null, 'kind' => 'improvement'],
            ]],
            ['version' => 'v2.3.0', 'weeks' => 1, 'features' => [
                ['title' => 'Webhook retries', 'description' => 'Failed deliveries are retried for 24 hours.', 'kind' => 'feature'],
                ['title' => 'Timezones on invoices', 'description' => null, 'kind' => 'fix'],
            ]],
            ['version' => 'v2.4.0', 'weeks' => 0, 'features' => [
                ['title' => 'Bulk export', 'description' => 'Every report, as CSV or JSON.', 'kind' => 'feature'],
                ['title' => 'Single sign-on', 'description' => 'SAML and OIDC.', 'kind' => 'feature'],
                ['title' => 'The v1 API is gone', 'description' => null, 'kind' => 'breaking'],
            ]],
        ];

        $listed = [];

        foreach ($catalogue as $release) {
            $releasedOn = Carbon::now()->subWeeks($release['weeks'])->startOfDay();

            // A release published today belongs on today's reading, so this
            // compares days rather than instants.
            if ($releasedOn->isAfter($moment->copy()->startOfDay())) {
                continue;
            }

            $listed[] = [
                'version' => $release['version'],
                'released_on' => $releasedOn->toDateString(),
                'title' => null,
                'summary' => null,
                'features' => $release['features'],
            ];
        }

        return array_reverse($listed);
    }

    /**
     * Fourteen days of prices that drift, with the changes between them.
     */
    private function seedHistory(CreepTarget $target, int $basePrice, string $brand, string $title): void
    {
        $previous = null;
        $price = $basePrice;

        foreach (range(14, 0) as $daysAgo) {
            $capturedAt = Carbon::now()->subDays($daysAgo);
            $price = max(500, $price + random_int(-400, 350));
            $availability = $daysAgo === 4 ? Availability::OutOfStock : Availability::InStock;

            $run = CreepRun::factory()->create([
                'creep_target_id' => $target->id,
                'started_at' => $capturedAt,
                'finished_at' => $capturedAt->addSeconds(4),
            ]);

            $snapshot = ProductSnapshot::factory()->create([
                'creep_run_id' => $run->id,
                'creep_target_id' => $target->id,
                'title' => $title,
                'brand' => $brand,
                'price_amount' => $price,
                'currency' => 'GBP',
                'availability' => $availability,
                'captured_at' => $capturedAt,
            ]);

            if ($previous instanceof ProductSnapshot) {
                $this->seedChanges($target, $previous, $snapshot, $capturedAt);
            }

            $previous = $snapshot;
        }
    }

    private function seedChanges(
        CreepTarget $target,
        ProductSnapshot $from,
        ProductSnapshot $to,
        Carbon $detectedAt,
    ): void {
        if ($from->price_amount !== $to->price_amount) {
            $target->changes()->create([
                'from_snapshot_id' => $from->id,
                'to_snapshot_id' => $to->id,
                'field' => 'price',
                'old_value' => $from->formattedPrice(),
                'new_value' => $to->formattedPrice(),
                'direction' => $to->price_amount > $from->price_amount
                    ? ChangeDirection::Up
                    : ChangeDirection::Down,
                'detected_at' => $detectedAt,
            ]);
        }

        if ($from->availability !== $to->availability) {
            $target->changes()->create([
                'from_snapshot_id' => $from->id,
                'to_snapshot_id' => $to->id,
                'field' => 'availability',
                'old_value' => $from->availability->label(),
                'new_value' => $to->availability->label(),
                'direction' => ChangeDirection::Changed,
                'detected_at' => $detectedAt,
            ]);
        }
    }
}
