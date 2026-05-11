<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superadmin');
    }

    public function rules(): array
    {
        // 'sometimes' means it only validates if the field is included in the request
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'plan' => ['sometimes', 'in:free,pro,enterprise'],
            'is_active' => ['sometimes', 'boolean'],
            'pf_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pf_client_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pf_webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pf_enabled' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
