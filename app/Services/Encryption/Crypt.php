<?php

declare(strict_types=1);

namespace App\Services\Encryption;

use App\Services\Encryption\Contract\DriverInterface;
use App\Services\Encryption\Contract\EncryptionInterface;

abstract class Crypt
{
    public static function getDriver(?string $name = null): DriverInterface
    {
        return \Hyperf\Context\ApplicationContext::getContainer()->get(EncryptionInterface::class)->getDriver($name);
    }

    public static function encrypt($value, bool $serialize = false, ?string $driverName = null): string
    {
        return static::getDriver($driverName)->encrypt($value, $serialize);
    }

    public static function decrypt(string $payload, bool $unserialize = false, ?string $driverName = null)
    {
        return static::getDriver($driverName)->decrypt($payload, $unserialize);
    }
}
