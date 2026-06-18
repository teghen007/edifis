<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class NodeModeUnsupportedException extends HttpException
{
    public function __construct(string $message = 'This feature requires cloud mode.', int $statusCode = 403)
    {
        parent::__construct($statusCode, $message);
    }
}
