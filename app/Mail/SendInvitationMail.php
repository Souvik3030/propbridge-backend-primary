<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviteUrl;

    public function __construct(public string $token, public string $companyName)
    {
        // Points to your React frontend's registration page with the secure token attached
        $frontendUrl =  config('app.frontend_url');
        $this->inviteUrl = $frontendUrl . '/register?token=' . $this->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join PropBridge!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.invitation',
            with: [
                'companyName' => $this->companyName, // 🔥 Pass it to the Blade file
            ],
        );
    }
}