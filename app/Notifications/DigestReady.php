<?php

namespace App\Notifications;

use App\Models\Competitor;
use App\Models\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
            ->markdown('mail.digest', [
                'business' => $business,
                'digest' => $this->digest,
                'sections' => $this->sections(),
                'url' => route('digests.show', $this->digest),
            ]);
    }

    /**
     * The report's sections, each paired with the competitor it is about.
     *
     * @return list<array{competitor: Competitor, summary: string, items: list<array{title: string, detail: string, url: string|null, date: string|null}>}>
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
                    'summary' => $section['summary'],
                    'items' => $section['items'],
                ];
            }
        }

        return $sections;
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
