<?php

declare(strict_types=1);

return [
    'create' => 1, // 每秒生成令牌數
    'consume' => 1, // 每次請求消耗令牌數
    'capacity' => 100, // 令牌桶最大容量
    'limitCallback' => [], // 觸發限流時回撥方法
    'waitTimeout' => 2, // 排隊超時時間
];
