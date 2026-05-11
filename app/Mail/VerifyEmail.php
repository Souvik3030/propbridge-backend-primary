<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $verificationUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your PropBridge Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email', 
            with: [
                'url' => $this->verificationUrl,
            ]
        );
    }
}