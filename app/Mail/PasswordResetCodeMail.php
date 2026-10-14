<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetCodeMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $code,
        public int $minutes,
        public string $purpose = 'reset',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your PSU Intern-Link verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-code');
    }
}
