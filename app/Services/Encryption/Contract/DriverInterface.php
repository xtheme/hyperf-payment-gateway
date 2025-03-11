<?php

declare(strict_types=1);

namespace App\Services\Encryption\Contract;

use App\Services\Encryption\Exception\DecryptException;
use App\Services\Encryption\Exception\EncryptException;

interface DriverInterface
{
    /**
     * Encrypt the given value.
     *
     * @throws EncryptException
     */
    public function encrypt($value, bool $serialize = false): string;

    /**
     * Decrypt the given value.
     *
     * @throws DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = false);
}
