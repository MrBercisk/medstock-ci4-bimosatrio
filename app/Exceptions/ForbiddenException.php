<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Anda tidak berhak melakukan tindakan ini.')
    {
        parent::__construct($message, 403);
    }
}
