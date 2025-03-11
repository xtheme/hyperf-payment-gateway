<?php

declare(strict_types=1);

namespace App\Payment\Contracts;

use Hyperf\HttpServer\Contract\RequestInterface;

interface DriverWithdrawInterface
{
    // 創建訂單, 返回支付網址
    public function withdrawCreate(RequestInterface $request);

    // 三方回調, 更新訂單狀態或金額
    public function withdrawNotify(RequestInterface $request);

    // 查詢訂單
    public function withdrawQuery(RequestInterface $request);

    // 模擬三方回調參數
    public function mockWithdrawNotify(string $orderNo);

    // 模擬三方查詢參數
    public function mockWithdrawQuery(string $orderNo);
}
