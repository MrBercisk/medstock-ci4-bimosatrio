<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Requests\Auth\LoginRequest;
use App\Services\AuthService;

class AuthController extends ApiController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login()
    {
        $form = new LoginRequest();
        $data = $this->input();

        if (! $form->validate($data)) {
            return ApiResponse::error('Data login tidak valid.', 422, $form->errors());
        }

        $user = $this->auth->attempt($data['email'], $data['password']);

        if ($user === null) {
            return ApiResponse::error('Email atau kata sandi salah.', 401);
        }

        return ApiResponse::success($user, 'Login berhasil.');
    }

    public function logout()
    {
        $this->auth->logout();

        return ApiResponse::success(null, 'Logout berhasil.');
    }

    public function me()
    {
        return ApiResponse::success($this->auth->currentUser());
    }
}
