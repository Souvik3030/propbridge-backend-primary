<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(public User $user, public string $token)
    {
        // Builds a link pointing to your React frontend, NOT the Laravel backend.
        // It passes both the token and the email in the URL.
        $frontendUrl = config('app.frontend_url'); // Or use env('FRONTEND_URL', 'http://localhost:5173')
        $this->resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->user->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your PropBridge Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-reset',
        );
    }
}