<?php

declare(strict_types=1);

namespace App\Payment\Contracts;

use Hyperf\HttpServer\Contract\RequestInterface;

interface DriverPaymentInterface
{
    // 創建訂單, 返回支付網址
    public function orderCreate(RequestInterface $request);

    // 三方回調, 更新訂單狀態或金額
    public function orderNotify(RequestInterface $request);

    // 查詢訂單
    public function orderQuery(RequestInterface $request);

    // 模擬三方回調參數
    public function mockNotify(string $orderNo);

    // 模擬三方查詢參數
    public function mockQuery(string $orderNo);

    // 转换三方订单状态, 返回统一的状态码到集成网关
    public function transformStatus($status);
}
