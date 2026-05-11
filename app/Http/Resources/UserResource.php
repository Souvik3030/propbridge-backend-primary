<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'company_id' => $this->company_id,
            
            // Safely attach the Company Resource if it was loaded
            'company' => new CompanyResource($this->whenLoaded('company')),
            
            // UI ke liye safe booleans aur strings
            'status' => $this->is_active ? 'Active' : 'Deactivated',
            'is_verified' => $this->email_verified_at !== null,
            
            // 🔥 FAANG FIX: Automatically attach permissions for the Logged-in User
            // This ensures Auth/Me and Auth/Login always have the ACL data.
            'permissions' => $this->when(
                $request->user()?->id === $this->id || isset($this->frontend_permissions), 
                fn() => $this->frontend_permissions ?? $this->getAllPermissions()->pluck('name')
            ),
            
            // Safe ISO dates for frontend parsing
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'joined_at' => $this->created_at?->format('M d, Y'),
            'pf_agent_id' => $this->pf_agent_id,
            
            // 🔥 FAANG FIX: Hybrid Safe Check for Stateless (Mobile) and Stateful (SPA) requests
            'is_impersonating' => $request->hasSession() ? $request->session()->has('impersonated_by') : false,
        ];
    }
}