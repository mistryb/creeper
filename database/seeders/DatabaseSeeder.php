<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Competitor;
use App\Models\Digest;
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
     * Gives a fresh install something to look at: a company with two
     * competitors and a digest already sent, so the dashboard is not empty on
     * first login.
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
            'last_digest_at' => Carbon::now()->subDay(),
            'next_digest_at' => Carbon::now()->addWeek(),
        ]);

        $user->switchBusiness($business);

        $acme = Competitor::factory()->for($business)->discovered()->create([
            'name' => 'Acme Insights',
            'url' => 'https://example.com',
            'reason' => 'The incumbent in product analytics, sold to the same teams on a bigger budget.',
        ]);

        $widgets = Competitor::factory()->for($business)->discovered()->create([
            'name' => 'Widgets Inc',
            'url' => 'https://example.org',
            'reason' => 'A newer rival with a free tier like yours, shipping quickly.',
        ]);

        Digest::factory()->for($business)->sent()->create([
            'report' => [
                'headline' => 'Acme raised prices again while Widgets shipped AI insights to its free tier.',
                'competitors' => [
                    [
                        'subject' => "competitor:{$acme->id}",
                        'summary' => 'Acme moved its Growth plan up and introduced a 14-day free trial.',
                        'items' => [
                            ['title' => 'Growth plan now $249/month', 'detail' => 'Up from $199, announced on their pricing page.', 'url' => 'https://example.com/pricing', 'date' => Carbon::now()->subDays(3)->toDateString()],
                            ['title' => '14-day free trial', 'detail' => 'The first trial Acme has offered without talking to sales.', 'url' => null, 'date' => null],
                        ],
                    ],
                    [
                        'subject' => "competitor:{$widgets->id}",
                        'summary' => 'Widgets shipped v2.6.0 with AI insights and widened the free tier.',
                        'items' => [
                            ['title' => 'v2.6.0: AI insights', 'detail' => 'Automatic summaries of funnels and sessions.', 'url' => 'https://example.org/changelog', 'date' => Carbon::now()->subDay()->toDateString()],
                            ['title' => 'Free tier now 10k events/month', 'detail' => 'Doubled, aimed squarely at small teams.', 'url' => null, 'date' => null],
                        ],
                    ],
                ],
                'notes' => null,
            ],
        ]);
    }
}
