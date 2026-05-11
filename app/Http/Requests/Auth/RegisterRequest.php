<?php
declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'exists:invitations,token'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invitation = Invitation::where('token', $this->token)->first();
                if ($invitation) {
                    if ($invitation->used_at !== null) $validator->errors()->add('token', 'Invitation already used.');
                    if ($invitation->expires_at < now()) $validator->errors()->add('token', 'Invitation expired.');
                }
            }
        ];
    }
}