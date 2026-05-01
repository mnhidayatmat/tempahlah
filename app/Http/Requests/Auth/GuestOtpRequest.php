<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GuestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'send' => [
                'identifier' => ['required', 'string', 'max:120'],
                'channel' => ['required', 'in:email,phone'],
            ],
            'verify' => [
                'identifier' => ['required', 'string', 'max:120'],
                'channel' => ['required', 'in:email,phone'],
                'code' => ['required', 'digits:6'],
            ],
            default => [],
        };
    }
}
