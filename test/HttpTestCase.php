<?php

declare(strict_types=1);

namespace HyperfTest;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Testing\Client;
use HyperfTest\Traits\InteractsWithAuthentication;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

/**
 * Class HttpTestCase.
 *
 * @method get($uri, $data = [], $headers = [])
 * @method post($uri, $data = [], $headers = [])
 * @method json($uri, $data = [], $headers = [])
 * @method file($uri, $data = [], $headers = [])
 * @method request($method, $path, $options = [])
 */
abstract class HttpTestCase extends TestCase
{
    use InteractsWithAuthentication;

    protected Client $client;

    protected Container|ContainerInterface|null $container;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    public function __call($name, $arguments)
    {
        $uri = $arguments[0];
        $data = $arguments[1] ?? [];
        $headers = $arguments[2] ?? [];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
            $arguments[2] = $headers;
        }

        return $this->client->{$name}($uri, $data ?? [], $headers);
    }

    public function setUp(): void
    {
        $this->container = ApplicationContext::getContainer();

        $this->client = make(Client::class);
    }

    /**
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        /** 清除已登入的用戶 */
        $this->token = '';
    }

    /**
     * 檢查陣列結構是否相同
     *
     * @param array $expected 期望的陣列
     * @param array $actural 實際的陣列
     */
    protected function assertArrayStructure(array $expected, array $actural): void
    {
        if (array_is_list($expected)) {
            $this->assertTrue(array_is_list($actural), 'array_is_list fail');

            return;
        }

        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actural);
            $actualValue = $actural[$key];

            if (is_string($value)) {
                $this->assertIsString($actualValue);
            }

            if (is_numeric($value)) {
                $this->assertIsNumeric($actualValue);
            }

            if (is_bool($value)) {
                $this->assertIsBool($actualValue);
            }

            if (is_array($value)) {
                $this->assertIsArray($actualValue);
                $this->assertArrayStructure($value, $actualValue);
            }
            unset($actualValue);
        }
    }

    protected function mock($abstract, \Closure $closure)
    {
        // 做一個 mock 的 UserQueueService，收到 logLogin 時，回傳假資料
        $mock = \Mockery::mock($abstract, function ($mock) use ($closure) {
            $closure($mock);
        });

        // 把 mock 的物件放到容器已解析的實體中
        $this->container->set($abstract, $mock);

        return $mock;
    }
}
