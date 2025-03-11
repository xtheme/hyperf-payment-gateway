<?php

declare(strict_types=1);

namespace App\Payment\Traits;

use Carbon\Carbon;

trait TimeTrait
{
    /**
     * 获取时间戳
     */
    public function getTimestamp(): string|int|float
    {
        return Carbon::now()->timestamp;
    }

    /**
     * 获取時區校正後的 年月日時分秒
     */
    public function getDateTime($format = 'Y-m-d H:i:s'): string
    {
        return Carbon::now($this->config['timezone'])->format($format);
    }

    /**
     * 获取伺服器時區的 年月日時分秒
     */
    public function getServerDateTime($format = 'Y-m-d H:i:s'): string
    {
        return Carbon::now()->format($format);
    }

    public function getServerDateTime8601(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
