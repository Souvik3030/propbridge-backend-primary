<?php
declare(strict_types=1);

namespace App\Actions\Audit;

use Illuminate\Support\Facades\DB;

class LogUserAction
{
    public function execute(string $userId, string $companyId, string $ip, string $action): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'company_id' => $companyId,
            'action' => $action,
            'resource_type' => 'App\Models\User',
            'resource_id' => $userId, // This is now safely saving the string UUID
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}