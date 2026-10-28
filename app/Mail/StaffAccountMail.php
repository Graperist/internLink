<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sign-in details for an account made by the Super Admin (an OJT
 * Coordinator). The password is temporary: the system asks for a new one
 * at the first sign-in.
 */
class StaffAccountMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $password,
        public string $roleName,
        public string $campusName,
        public bool $isReset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isReset
            ? 'Your new PSU Intern-Link password'
            : 'Your PSU Intern-Link '.$this->roleName.' account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-account', with: ['loginUrl' => route('login')]);
    }
}
