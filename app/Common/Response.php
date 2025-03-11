<?php

declare(strict_types=1);

namespace App\Common;

use App\Constants\ErrorCode;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;

class Response
{
    public static function success(array $data = []): Psr7ResponseInterface
    {
        return static::result(ErrorCode::SUCCESS, ErrorCode::getMessage(ErrorCode::SUCCESS), $data);
    }

    public static function error(string $message = '', int $code = ErrorCode::ERROR, array $data = []): Psr7ResponseInterface
    {
        if (empty($message)) {
            $message = ErrorCode::getMessage($code);
        }

        stdLog()->error($message, $data);

        return static::result($code, $message, $data);
    }

    protected static function result($code, $message, $data): Psr7ResponseInterface
    {
        if (!$data) {
            $data = new \stdClass();
        }

        $output = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        return response()->withStatus($code)
            ->withAddedHeader('content-type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream(json_encode($output, JSON_UNESCAPED_SLASHES)));
    }
}
