<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AccountSuspendedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAccountSuspendedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $targetUser,
        public readonly User $adminUser
    ) {}

    public function handle(): void
    {
        // Fail safely if the target user lacks an email (edge case protection)
        if (!$this->targetUser->email) {
            Log::warning("Skipped suspension email: User ID {$this->targetUser->id} has no email.");
            return;
        }

        Mail::to($this->targetUser->email)->send(
            new AccountSuspendedMail($this->targetUser, $this->adminUser)
        );
    }
}