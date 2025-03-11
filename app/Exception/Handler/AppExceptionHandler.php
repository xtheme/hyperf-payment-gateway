<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Common\Response;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $stdoutLogger)
    {
    }

    public function handle(\Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $log = sprintf('%s in %s on line %s', $throwable->getMessage(), $throwable->getFile(), $throwable->getLine());

        $trace = $throwable->getTraceAsString();

        stdLog()->error($log);
        stdLog()->error($trace);

        return Response::error($log);
    }

    public function isValid(\Throwable $throwable): bool
    {
        return true;
    }
}
