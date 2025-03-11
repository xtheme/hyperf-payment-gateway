<?php

declare(strict_types=1);

namespace App\Payment;

use App\Exception\ApiException;
use App\Payment\Contracts\PaymentGatewayInterface;
use Hyperf\Contract\ConfigInterface;

use function Hyperf\Support\make;

/**
 * 支付類渠道
 */
class PaymentGateway implements PaymentGatewayInterface
{
    /**
     * The config instance.
     */
    protected ConfigInterface $config;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @throws ApiException
     */
    public function getDriver(string $name)
    {
        $name = strtolower($name);
        $config = $this->config->get('payment.driver.' . $name);

        if (empty($config) or empty($config['class'])) {
            throw new ApiException(sprintf('The payment driver %s doesn\'t found.', $name));
        }

        $driverClass = $config['class'];

        // merge config
        $driverConfig = $config['config'] ?? [];

        return make($driverClass, ['config' => $driverConfig]);
    }
}
