<?php

namespace App\Http\Requests\Invitation;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['superadmin', 'admin']);
    }

    public function rules(): array
    {
        $user = $this->user();
        $isSuperAdmin = $user->hasRole('superadmin');
        $isAdmin = $user->hasRole('admin');
        $allowedRoles = 'admin,agent,owner';

        $rules = [
            'email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    // 🔥 FAANG FIX: Use withoutGlobalScopes() to scan the ENTIRE database
                    // This prevents an Admin from inviting an email that belongs to another company
                    if (User::withoutGlobalScopes()->where('email', $value)->exists()) {
                        $fail('A user with this email already exists in the system.');
                    }
                    
                    // 🔥 FAANG FIX: Check active invites globally as well
                    $activeInvite = Invitation::withoutGlobalScopes()
                        ->where('email', $value)
                        ->whereNull('used_at')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($activeInvite) {
                        $fail('An active invitation already exists for this email.');
                    }
                }
            ],
            'role' => ['required', 'string', 'in:' . $allowedRoles],
            'is_send_now' => ['required', 'boolean'], 
        ];

        if ($isSuperAdmin) {
            $rules['company_id'] = ['required', 'uuid', 'exists:companies,id'];
        }

        return $rules;
    }

    public function resolveCompanyId(): string
    {
        return $this->user()->hasRole('superadmin') 
            ? $this->input('company_id') 
            : $this->user()->company_id;
    }
}