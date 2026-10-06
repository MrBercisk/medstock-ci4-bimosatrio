<?php

namespace App\Exceptions;

/* Exception untuk error karena aturan bisnis */
class BusinessRuleException extends ApiException
{
    /** 
      * @param string $message Pesan error. 
      * @param array|null $errors Detail error jika ada. 
    */
    public function __construct(string $message, ?array $errors = null)
    {
        parent::__construct($message, 422, $errors);
    }
}
