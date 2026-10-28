<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The HTE representative's sign-in details, sent to the email the
 * coordinator entered (prefilled from the partner agency's contact email),
 * so the HTE can log in and evaluate their interns.
 */
class HteAccountMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $password,
        public string $hteName,
        public string $campusName,
        public bool $isReset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isReset
            ? 'Your new PSU Intern-Link password'
            : 'Your PSU Intern-Link HTE account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hte-account', with: ['loginUrl' => route('login')]);
    }
}
