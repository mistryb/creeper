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
        return (new MailMessage)
            ->subject(__('Your Creeper sign-in code: :code', ['code' => $this->code]))
            ->view(['mail.login-code', 'mail.login-code-text'], [
                'code' => $this->code,
                'minutes' => LoginCodes::TTL_MINUTES,
            ]);
    }
}
