<?php

namespace App\Filters;

use App\Libraries\ApiResponse;
use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/* filter untuk memastikan user sudah login */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // tolak request jika user belum login
        if ((new AuthService())->currentUser() === null) {
            return ApiResponse::error('Anda belum login.', 401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
