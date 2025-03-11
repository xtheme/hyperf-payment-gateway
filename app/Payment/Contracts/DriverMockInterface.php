<?php

declare(strict_types=1);

namespace App\Payment\Contracts;

interface DriverMockInterface
{
    // 創建訂單, 返回支付網址
    public function request(string $orderNo);
}
