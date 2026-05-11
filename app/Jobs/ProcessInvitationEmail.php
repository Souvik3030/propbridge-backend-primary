<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SendInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessInvitationEmail implements ShouldQueue
{
    // Dispatchable: Allows ProcessInvitationEmail::dispatch()
    // InteractsWithQueue: Allows the job to be released back to the queue or checked for attempts
    // Queueable: Allows assigning connections/queues
    // SerializesModels: Safely serializes Eloquent models (if we passed any)
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 🔥 FAANG STANDARD: Fault Tolerance.
     * Agar SMTP server down hai, toh app crash nahi hogi. 
     * Valkey is job ko maximum 3 baar retry karega.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     * FAANG STRICTNESS: Using PHP 8.1 'readonly' ensures data immutability in the queue.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $companyName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Asli I/O blocking kaam yahan hoga, background mein, user ki request se dur.
        Mail::to($this->email)->send(new SendInvitationMail($this->token, $this->companyName));
    }

    /**
     * 🔥 FAANG STANDARD: Exponential Backoff.
     * Agar pehli baar fail hua, toh 10 seconds wait karo.
     * Doosri baar fail hua, toh 30 seconds wait karo.
     * Teesri baar fail hua, toh 60 seconds wait karo.
     * Isse SMTP provider par spam attack nahi hota aur Rate Limits cross nahi hote.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}