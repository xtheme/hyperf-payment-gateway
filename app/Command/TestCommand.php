<?php

declare(strict_types=1);

namespace App\Command;

use App\Payment\OrderDrivers\CacheOrder;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

use function Hyperf\Support\make;

#[Command]
class TestCommand extends HyperfCommand
{
    protected ?string $name = 'test';

    protected string $description = '測試用';

    public function handle(): void
    {
        $orderNo = 'paypal_1717645310095';
        $orderDriver = make(CacheOrder::class);
        $order = $orderDriver->getOrder($orderNo);
        d($order);
    }
}
