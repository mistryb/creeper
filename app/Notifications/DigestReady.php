<?php

namespace App\Notifications;

use App\Models\Competitor;
use App\Models\Digest;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The digest itself, in the owner's inbox.
 *
 * One email per digest: the headline, then a section per competitor with
 * what they did and where it was found. Competitor names are looked up when
 * the mail is rendered, so a rename shows up and a deleted competitor's
 * section is dropped rather than shown under a stale name.
 */
class DigestReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Digest $digest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->digest->business;

        return (new MailMessage)
            ->subject(sprintf('%s competitor digest · %s', $business->name, $this->digest->period_end->toFormattedDateString()))
            ->view(['mail.digest', 'mail.digest-text'], [
                'business' => $business,
                'digest' => $this->digest,
                'sections' => $this->sections(),
                'url' => route('digests.show', $this->digest),
                'settingsUrl' => route('businesses.show', $business),
            ]);
    }

    /**
     * The report's sections, each paired with the competitor it is about.
     *
     * Dates arrive as the model wrote them (`2026-10-10`) and leave as a short
     * "Oct 10"; one it wrote some other way is shown as it is.
     *
     * @return list<array{competitor: Competitor, host: string|null, summary: string, items: list<array{title: string, detail: string, url: string|null, date: string|null}>}>
     */
    public function sections(): array
    {
        $competitors = $this->digest->business->competitors()->get()->keyBy(fn (Competitor $competitor): string => $competitor->subject());
        $sections = [];

        foreach ($this->digest->report['competitors'] ?? [] as $section) {
            $competitor = $competitors->get($section['subject']);

            if ($competitor instanceof Competitor) {
                $sections[] = [
                    'competitor' => $competitor,
                    'host' => $this->host($competitor->url),
                    'summary' => $section['summary'],
                    'items' => array_map(fn (array $item): array => [
                        ...$item,
                        'date' => $this->shortDate($item['date'] ?? null),
                    ], $section['items']),
                ];
            }
        }

        return $sections;
    }

    /**
     * A competitor's website as a reader would say it: no scheme, no `www.`.
     */
    private function host(?string $url): ?string
    {
        $host = $url === null ? null : parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::after($host, 'www.') : null;
    }

    private function shortDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('M j');
        } catch (InvalidFormatException) {
            return $date;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'digest_id' => $this->digest->id,
            'headline' => $this->digest->report['headline'] ?? null,
        ];
    }
}
