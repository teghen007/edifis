<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationException extends HttpException
{
    public function __construct(string $code, string $message, int $statusCode = 401)
    {
        parent::__construct($statusCode, $message);
        $this->code = $code;
    }
}
