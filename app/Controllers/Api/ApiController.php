<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

abstract class ApiController extends BaseController
{
    /* Membaca body JSON atau form, tanpa melempar exception jika JSON rusak */
    protected function input(): array
    {
        if (str_contains($this->request->getHeaderLine('Content-Type'), 'application/json')) {
            $data = json_decode($this->request->getBody() ?? '', true);

            return is_array($data) ? $data : [];
        }

        return $this->request->getPost() ?? [];
    }
}
