<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Mail\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;

class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 🔥 FAANG STANDARD: Retry 3 times if SMTP fails
    public int $tries = 3;

    // 🔥 FAANG STANDARD: 'readonly' protects memory state in the queue
    public function __construct(public readonly User $user) {}

    public function handle(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $this->user->id, 'hash' => sha1($this->user->email)]
        );

        Mail::to($this->user->email)->send(new VerifyEmail($verificationUrl));
    }

    // 🔥 FAANG STANDARD: Wait 10s, 30s, then 60s before retrying
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}