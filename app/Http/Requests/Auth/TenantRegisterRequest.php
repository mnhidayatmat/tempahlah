<?php

namespace App\Http\Requests\Auth;

use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TenantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Store the phone in E.164 ("+60123456789") whatever the host typed —
     * the front-end prefills "+60", but the form can be posted without it.
     * The value is used for WhatsApp + wa.me links downstream. Unparseable
     * input is left verbatim so the regex rule reports it rather than us
     * silently blanking it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\-\s]{8,20}$/'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'business_name' => ['required', 'string', 'max:120'],
            'locale' => ['nullable', 'in:ms,en'],
            'terms' => ['accepted'],
        ];
    }
}
