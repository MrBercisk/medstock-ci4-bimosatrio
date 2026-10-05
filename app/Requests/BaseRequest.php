<?php

namespace App\Requests;

use Config\Services;

/* Base class untuk validasi request. */
abstract class BaseRequest
{
    protected array $errors = [];

    /* Aturan validasi request. */
    abstract protected function rules(): array;

    /*  Pesan validasi custom. */
    protected function messages(): array
    {
        return [];
    }

    /* Jalankan validasi data. */
    public function validate(array $data): bool
    {
        $validation = Services::validation(null, false);
        $validation->setRules($this->rules(), $this->messages());

        $passed       = $validation->run($data);
        $this->errors = $validation->getErrors();

        return $passed;
    }

    /* Ambil error validasi. */
    public function errors(): array
    {
        return $this->errors;
    }
}
