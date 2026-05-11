<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
   public function toArray(Request $request): array
    {
        // 🔥 Correct Config Path
        $planQuotas = config('saas.plans'); 
        $planKey = strtolower($this->plan ?? 'free');
        $maxUsers = $planQuotas[$planKey]['max_users'] ?? 5;

        $activeUsersCount = $this->users_count ?? 0;
        
        // 🔥 Read from eager loaded count to prevent N+1
        $pendingInvitesCount = $this->pending_invites_count ?? 0;

        $totalUsed = $activeUsersCount + $pendingInvitesCount;
        $availableSeats = $maxUsers - $totalUsed;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'plan' => ucfirst($planKey),
            'status' => $this->is_active ? 'Active' : 'Inactive',
            
            'quota' => [
                'max_users' => $maxUsers === 999999 ? 'Unlimited' : $maxUsers,
                'used_users' => $totalUsed,
                'available_seats' => $availableSeats < 0 ? 0 : $availableSeats,
                'is_full' => $totalUsed >= $maxUsers,
            ],
            
            'pf_client_id' => $this->pf_client_id,
            'pf_client_secret' => $this->pf_client_secret ? '********' : null, // Masked for security
            'pf_webhook_secret' => $this->pf_webhook_secret ? '********' : null,
            'pf_enabled' => (bool)$this->pf_enabled,
            
            'integrations' => [
                'has_property_finder' => $this->hasPropertyFinderEnabled(),
                'has_bitrix' => !empty($this->bitrix_oauth_token),
            ],
            'metrics' => [
                'users_count' => $this->whenCounted('users'),
                'pending_invites' => $pendingInvitesCount,
            ],
            'logo_url' => $this->logo_url,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
