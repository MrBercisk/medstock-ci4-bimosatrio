<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;

/* Helper untuk response API */
class ApiResponse
{
    /* Response sukses */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200
    ): ResponseInterface {
        return self::response([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /* Response error */
    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null
    ): ResponseInterface {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return self::response($body, $status);
    }

    /* Response format JSON */
    private static function response(array $body, int $status): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON($body);
    }
}
