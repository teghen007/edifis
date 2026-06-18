<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ConsentRequiredException extends HttpException
{
    public function __construct(string $message = 'Consent required for minor enrolment.', int $statusCode = 422)
    {
        parent::__construct($statusCode, $message);
    }
}
