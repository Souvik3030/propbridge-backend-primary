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

class AccountActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $targetUser,
        public readonly User $adminUser
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Good News: Your PropBridge Account is Active',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.activated',
        );
    }
}
