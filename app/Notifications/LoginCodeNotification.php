<?php

namespace App\Notifications;

use App\Auth\LoginCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying a sign-in code.
 *
 * Sent on demand rather than to a user, because a code is issued before we
 * know whether an account exists — the address might be signing up.
 */
class LoginCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        protected string $code,
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
        $minutes = LoginCodes::TTL_MINUTES;

        return (new MailMessage)
            ->subject(__('Your Creeper sign-in code: :code', ['code' => $this->code]))
            ->greeting(__('Your sign-in code'))
            ->line(__('Enter this code to finish signing in to Creeper.'))
            /*
             * The code sits on its own line, spaced, because it is going to be
             * read off a screen and typed on another device.
             */
            ->line('**'.implode(' ', str_split($this->code)).'**')
            ->line(__('It expires in :minutes minutes and can only be used once.', ['minutes' => $minutes]))
            ->line(__('If you did not ask to sign in, you can ignore this email — nobody can get in without the code.'));
    }
}
