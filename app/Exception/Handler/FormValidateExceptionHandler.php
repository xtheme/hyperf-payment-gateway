<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Common\Response;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

class FormValidateExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        if ($throwable instanceof ValidationException) {
            $this->stopPropagation();

            return Response::error($throwable->validator->errors()->first());
        }

        return $response;
    }

    public function isValid(\Throwable $throwable): bool
    {
        return true;
    }
}
