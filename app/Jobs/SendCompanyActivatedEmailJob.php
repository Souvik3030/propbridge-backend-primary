<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CompanyActivatedMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCompanyActivatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly User $superAdmin
    ) {}

    public function handle(): void
    {
        $companyAdmins = User::where('company_id', $this->company->id)
            ->role('admin')
            ->get();

        foreach ($companyAdmins as $admin) {
            if ($admin->email) {
                Mail::to($admin->email)->send(
                    new CompanyActivatedMail($this->company, $this->superAdmin, $admin)
                );
            }
        }
    }
}
