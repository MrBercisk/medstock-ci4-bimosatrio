<?php

namespace App\Exceptions;

/* exception untuk data yang tidak ditemukan */
class NotFoundException extends ApiException
{
    /**  @param string $message Pesan error. */
    public function __construct(string $message = 'Data tidak ditemukan.')
    {
        parent::__construct($message, 404);
    }
}
