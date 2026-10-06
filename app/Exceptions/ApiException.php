<?php

namespace App\Exceptions;

use RuntimeException;

/* exception dasar untuk error pada API */
class ApiException extends RuntimeException
{
    /** 
     * * @param string $message Pesan error. 
     * * @param int $status HTTP status code. 
     * * @param array|null $errors Detail error tambahan. */
    public function __construct(string $message, private int $status = 400, private ?array $errors = null)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): ?array
    {
        return $this->errors;
    }
}
