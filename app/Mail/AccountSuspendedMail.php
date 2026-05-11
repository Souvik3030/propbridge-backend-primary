<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $targetUser,
        public readonly User $adminUser
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notice: Your PropBridge Account Status',
        );
    }

    public function content(): Content
    {
        // We will use Laravel's Markdown mail components for a clean SaaS look
        return new Content(
            view: 'emails.account.suspended',
        );
    }
}