<?php

namespace App\Requests\Auth;

use App\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|valid_email|max_length[150]',
            'password' => 'required|max_length[255]',
        ];
    }

    protected function messages(): array
    {
        return [
            'email' => [
                'required'    => 'Email wajib diisi.',
                'valid_email' => 'Format email tidak valid.',
            ],
            'password' => [
                'required' => 'Kata sandi wajib diisi.',
            ],
        ];
    }
}
