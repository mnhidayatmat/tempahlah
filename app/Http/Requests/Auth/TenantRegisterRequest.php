<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TenantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\-\s]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'business_name' => ['required', 'string', 'max:120'],
            'locale' => ['nullable', 'in:ms,en'],
            'terms' => ['accepted'],
        ];
    }
}
