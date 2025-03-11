<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use FriendsOfHyperf\Http\Client\ConnectionException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;

class ApiExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        // CURL 請求異常
        if ($throwable instanceof ConnectionException) {
            // 阻止異常冒泡
            $this->stopPropagation();

            return Response::error($throwable->getMessage(), 500);
        }

        // 判斷被捕獲到的異常是希望被捕獲的異常
        if ($throwable instanceof ApiException) {
            $status_code = 0 == $throwable->getCode() ? 500 : $throwable->getCode();
            $message = $throwable->getMessage() ?? ErrorCode::getMessage($status_code);

            // 阻止異常冒泡
            $this->stopPropagation();

            return Response::error($message, $status_code);
        }

        // 交給下一個異常處理器
        return $response;
        // 或者不做處理直接遮蔽異常
    }

    /**
     * 判斷該異常處理器是否要對該異常進行處理
     */
    public function isValid(\Throwable $throwable): bool
    {
        return true;
    }
}
