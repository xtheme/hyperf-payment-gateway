<?php

declare(strict_types=1);

namespace App\Services\Encryption\Contract;

interface EncryptionInterface extends DriverInterface
{
    /**
     * Get a driver instance.
     */
    public function getDriver(?string $name = null): DriverInterface;
}
