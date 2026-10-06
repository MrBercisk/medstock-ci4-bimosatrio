<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\ApiResponse;
use App\Services\AuthService;
use Throwable;

abstract class ApiController extends BaseController
{
    /** mengambil data dari request JSON atau form. */
    protected function input(): array
    {
        if (str_contains($this->request->getHeaderLine('Content-Type'), 'application/json')) {
            $data = json_decode($this->request->getBody() ?? '', true);

            return is_array($data) ? $data : [];
        }

        return $this->request->getPost() ?? [];
    }

    /** Mengambil data user yang sedang login dari session */
    protected function user(): array
    {
        return (new AuthService())->currentUser()
            ?? throw new ApiException('Anda belum login.', 401);
    }

    /** Menjalankan action dan menangani exeception menjadi response JSON */
    protected function handle(callable $action)
    {
        try {
            return $action();
        } catch (ApiException $e) {
            return ApiResponse::error($e->getMessage(), $e->status(), $e->errors());
        } catch (Throwable $e) {
            log_message('error', $e->getMessage());

            return ApiResponse::error('Terjadi kesalahan pada server.', 500);
        }
    }
}
