<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 🔥 FIX: Return true. Authorization is now handled entirely by CompanyPolicy.
        return true; 
    }

    public function rules(): array
    {
        // 2. Validation rules
        return [
            'name' => ['required', 'string', 'max:255','unique:companies,name'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'domain' => ['required', 'string', 'max:255', 'unique:companies,domain'],
            'plan' => ['required', 'in:free,pro,enterprise'],
            'logo_url'           => ['nullable', 'url', 'max:2048'],
            'pf_client_id'       => ['nullable', 'string', 'max:255'],
            'pf_client_secret'   => ['nullable', 'string', 'max:255'],
            'pf_webhook_secret'  => ['nullable', 'string', 'max:255'],
            'pf_enabled'         => ['nullable', 'boolean'],
            'bitrix_oauth_token' => ['nullable', 'string'],
            'settings'           => ['nullable', 'array'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}