<?php

declare(strict_types=1);

namespace App\Payment\Contracts;

interface PaymentGatewayInterface
{
    public function getDriver(string $name);
}
