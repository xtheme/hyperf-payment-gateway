<?php

declare(strict_types=1);

use App\Logger\StdoutLoggerFactory;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\PaymentGateway;
use App\Services\Encryption\Contract\EncryptionInterface;
use App\Services\Encryption\EncryptionManager;
use Hyperf\Contract\StdoutLoggerInterface;

return [
    StdoutLoggerInterface::class => StdoutLoggerFactory::class,
    EncryptionInterface::class => EncryptionManager::class,
    PaymentGatewayInterface::class => PaymentGateway::class,
];
