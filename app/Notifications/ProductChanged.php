<?php

namespace App\Notifications;

use App\Models\CreepChange;
use App\Models\CreepTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that something moved on a target they're watching.
 */
class ProductChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, CreepChange>  $changes
     */
    public function __construct(
        public CreepTarget $target,
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
            ->line(sprintf('Creeper spotted a change on **%s**.', $this->target->displayName()));

        foreach ($this->changes as $change) {
            $message->line('- '.$change->describe());
        }

        return $message
            ->action('View the target', route('creep-targets.show', $this->target))
            ->line('You can turn these off from the target\'s settings.');
    }

    /**
     * Lead with the price when there is one — it's what people actually care about.
     */
    protected function subject(): string
    {
        foreach ($this->changes as $change) {
            if ($change->field === 'price') {
                return sprintf('%s: %s', $this->target->displayName(), $change->describe());
            }
        }

        $first = $this->changes[0] ?? null;

        return $first instanceof CreepChange
            ? sprintf('%s: %s', $this->target->displayName(), $first->describe())
            : sprintf('%s changed', $this->target->displayName());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'creep_target_id' => $this->target->id,
            'changes' => array_map(fn (CreepChange $change): string => $change->describe(), $this->changes),
        ];
    }
}
