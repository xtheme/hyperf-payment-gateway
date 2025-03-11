<?php

declare(strict_types=1);

namespace App\Services\Encryption;

use App\Exception\ApiException;
use App\Services\Encryption\Contract\DriverInterface;
use App\Services\Encryption\Contract\EncryptionInterface;
use Hyperf\Contract\ConfigInterface;

use function Hyperf\Support\make;

class EncryptionManager implements EncryptionInterface
{
    /**
     * The config instance.
     *
     * @var ConfigInterface
     */
    protected $config;

    /**
     * The array of created "drivers".
     *
     * @var DriverInterface[]
     */
    protected $drivers = [];

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function encrypt($value, bool $serialize = false): string
    {
        return $this->getDriver()->encrypt($value, $serialize);
    }

    public function decrypt(string $payload, bool $unserialize = false)
    {
        return $this->getDriver()->decrypt($payload, $unserialize);
    }

    /**
     * Get a driver instance.
     */
    public function getDriver(?string $name = null): DriverInterface
    {
        if (isset($this->drivers[$name]) && $this->drivers[$name] instanceof DriverInterface) {
            return $this->drivers[$name];
        }

        $name = $name ?: $this->config->get('encryption.default', 'aes');

        $config = $this->config->get('encryption.driver.' . $name);

        if (empty($config) or empty($config['class'])) {
            throw new ApiException(sprintf('The encryption driver config %s is invalid.', $name));
        }

        $driverClass = $config['class'];

        $driver = make($driverClass, ['options' => $config['options'] ?? []]);

        return $this->drivers[$name] = $driver;
    }
}
