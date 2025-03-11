<?php

declare(strict_types=1);

namespace App\Command\PaymentCommand;

use Hyperf\Command\Annotation\Command;

#[Command]
class WithdrawNotifyCommand extends BaseCommand
{
    protected string $target = 'withdraw-notify';
}
