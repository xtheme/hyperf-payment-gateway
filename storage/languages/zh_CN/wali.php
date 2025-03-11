<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'gameReason' => [
        'transfer_failed' => '划拨失败，不继续进游戏',
        'user_banned' => '⽤户被封禁',
        'ip_banned' => '地区限制',
        'illegal_game' => '不⽀持商户使⽤此游戏类型',
    ],
    'login' => [
        'game_requests' => '游戏参数不完整',
        'orderId_requests' => '划拨参数不完整',
    ],
    'transfer' => [
        'illegal_time' => '划拨请求已超时！',
        'illegal_credit' => '订单⾦额不能为 0',
        'conflict_credit' => '订单⾦额与请求不⼀致！',
        'not_found' => '查无划订单号！',
        'processing' => '划拨正在处理中，请稍候再试！',
        'ok' => '划拨成功！',
        'duplicate' => '请勿重复划拨！',
        'credit_not_enough' => '⽤户余额不⾜！',
        'credit_overflow' => '⽤户余额溢出！',
        'agent_credit_not_enough' => '商户额度不⾜！',
        'game_limit' => '请先退出游戏后再进行划拨！',
        'user_banned' => '⽤户被⽡⼒封禁！',
    ],
];
