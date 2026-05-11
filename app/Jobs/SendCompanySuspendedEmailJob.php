<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CompanySuspendedMail; // You will need to create this Mailable
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCompanySuspendedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly User $superAdmin
    ) {}

    public function handle(): void
    {
        // 🔥 FAANG FIX: Only notify the people who actually have the authority to fix the billing/compliance issue
        // Fetch all users in this company who hold the 'admin' role
        $companyAdmins = User::where('company_id', $this->company->id)
            ->role('admin') // Assumes you are using Spatie Permission
            ->get();

        foreach ($companyAdmins as $admin) {
            if ($admin->email) {
                Mail::to($admin->email)->send(
                    new CompanySuspendedMail($this->company, $this->superAdmin, $admin)
                );
            }
        }
    }
}