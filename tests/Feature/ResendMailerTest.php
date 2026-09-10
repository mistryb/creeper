<?php

use Illuminate\Support\Facades\Mail;

/*
 * Sign-in is an emailed six digit code and nothing else, so an install whose
 * mailer cannot be built is an install nobody — including its owner — can get
 * into. `config/mail.php` has carried a `resend` mailer since Laravel shipped
 * one, but the transport behind it comes from resend/resend-laravel. Drop the
 * package and the config still looks right, and production fails at the first
 * sign-in instead of at deploy.
 */
it('builds the resend mailer, so an install can send sign-in codes', function () {
    config(['services.resend.key' => 'test-key']);

    expect(Mail::mailer('resend'))->not->toBeNull();
});
