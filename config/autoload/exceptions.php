<?php

declare(strict_types=1);

use App\Exception\Handler\ApiExceptionHandler;
use App\Exception\Handler\FormValidateExceptionHandler;

return [
    'handler' => [
        'http' => [
            Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler::class,
            Hyperf\ExceptionHandler\Handler\WhoopsExceptionHandler::class,
            ApiExceptionHandler::class,
            FormValidateExceptionHandler::class,
            App\Exception\Handler\AppExceptionHandler::class,
        ],
    ],
];
