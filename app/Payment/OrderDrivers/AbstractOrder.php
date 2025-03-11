<?php

declare(strict_types=1);

namespace App\Payment\OrderDrivers;

use App\Exception\ApiException;
use App\Payment\Traits\TimeTrait;
use FriendsOfHyperf\Cache\Facade\Cache;
use Hyperf\Contract\StdoutLoggerInterface;

abstract class AbstractOrder
{
    use TimeTrait;

    protected StdoutLoggerInterface $logger;

    public function __construct()
    {
        $this->logger = stdLog();
    }

    /**
     * 取出表單內容
     */
    public function getFormData(string $orderNo, string $type = 'form')
    {
        $cacheKey = $this->getCacheKey($orderNo, $type);

        $form = Cache::get($cacheKey);

        if (!$form) {
            throw new ApiException('表單记录不存在或已过期 ' . $orderNo);
        }

        return $form;
    }

    /**
     * 緩存表單內容
     * 綠界支付網址, 需要以表單的方式送出轉跳到綠界收銀台頁面取得
     * 必須產生一個中繼網址讓四方訪問後轉發到綠界收銀台
     */
    public function createFormData(string $orderNo, array $data, string $type = 'form'): void
    {
        $cacheKey = $this->getCacheKey($orderNo, $type);

        $log = sprintf('%s: %s createFormData', $type, $orderNo);
        $this->logger->info($log, $data);

        Cache::put($cacheKey, $data, 86400 * 90);
    }

    protected function getCacheKey(string $orderNo, string $type = 'order'): string
    {
        return sprintf('%s:%s', $type, $orderNo);
    }
}
