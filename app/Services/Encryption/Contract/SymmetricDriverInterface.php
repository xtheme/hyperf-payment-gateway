<?php

declare(strict_types=1);

namespace App\Services\Encryption\Contract;

interface SymmetricDriverInterface extends DriverInterface
{
    public static function generateKey(array $options = []): string;

    public function getKey(): string;
}
