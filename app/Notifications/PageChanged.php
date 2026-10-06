<?php

namespace App\Notifications;

use App\Enums\ChangeKind;
use App\Models\CreepChange;
use App\Models\WatchedPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that something moved on a page they're watching.
 *
 * One e-mail per run, listing every fact that appeared, went or changed
 * value as a {@see CreepChange} line.
 */
class PageChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, CreepChange>  $changes
     */
    public function __construct(
        public WatchedPage $watchedPage,
        public array $changes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Something moved.')
            ->line(sprintf('Creeper spotted a change on **%s**.', $this->title()));

        foreach ($this->changes as $change) {
            $message->line('- '.$change->describe());
        }

        return $message
            ->action('View the page', route('watched-pages.show', $this->watchedPage))
            ->line('You can turn these off from the page\'s settings.');
    }

    /**
     * Lead with a value that moved, since that is usually the news; failing
     * that, whatever appeared or went first.
     */
    protected function subject(): string
    {
        $lead = collect($this->changes)->first(fn (CreepChange $change): bool => $change->kind === ChangeKind::Changed)
            ?? ($this->changes[0] ?? null);

        return $lead instanceof CreepChange
            ? sprintf('%s: %s', $this->title(), $lead->describe())
            : sprintf('%s changed', $this->title());
    }

    /**
     * The page, named with the competitor it belongs to.
     */
    protected function title(): string
    {
        return sprintf('%s · %s', $this->watchedPage->competitor->name, $this->watchedPage->displayName());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'watched_page_id' => $this->watchedPage->id,
            'changes' => array_map(fn (CreepChange $change): string => $change->describe(), $this->changes),
        ];
    }
}
