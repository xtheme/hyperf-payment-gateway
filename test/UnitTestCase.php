<?php

declare(strict_types=1);

namespace HyperfTest;

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\Container;
use Hyperf\Stringable\Str;
use HyperfTest\Traits\DatabaseTransactions;
use Mockery\Exception\InvalidCountException;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\class_uses_recursive;

abstract class UnitTestCase extends TestCase
{
    protected array $mockedClasses = [];

    protected Container|ContainerInterface|null $container;

    protected array $beforeApplicationDestroyedCallbacks = [];

    protected array $connectionsToTransact = ['default', 'log-db'];

    protected ?\Throwable $callbackException = null;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    public function setUp(): void
    {
        // 重設 container，因為執行所有 test case 時
        // container 內的 class 所參照的 object 可能會影響下一次測試
        $container = require BASE_PATH . '/config/container.php';
        $container->get(ApplicationInterface::class);
        $this->container = $container;

        $this->setUpTraits();
    }

    /**
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        if ($this->container) {
            $this->callBeforeApplicationDestroyedCallbacks();
        }

        if (class_exists('Mockery')) {
            if ($container = \Mockery::getContainer()) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            try {
                \Mockery::close();
            } catch (InvalidCountException $e) {
                if (!Str::contains($e->getMethodName(), ['doWrite', 'askQuestion'])) {
                    $this->callbackException = $e;
                }
            }
        }

        if ($this->callbackException) {
            throw $this->callbackException;
        }
    }

    public function spy($abstract): LegacyMockInterface|MockInterface
    {
        $mock = \Mockery::spy($abstract);

        $this->container->set($abstract, $mock);

        return $mock;
    }

    protected function setUpTraits(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[DatabaseTransactions::class])) {
            $this->beginDatabaseTransaction();
        }
    }

    protected function beforeApplicationDestroyed(callable $callback): void
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    /**
     * Execute the application's pre-destruction callbacks.
     */
    protected function callBeforeApplicationDestroyedCallbacks(): void
    {
        foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                if (!$this->callbackException) {
                    $this->callbackException = $e;
                }
            }
        }
    }

    protected function mock($abstract, \Closure $closure): LegacyMockInterface|MockInterface
    {
        // 做一個 mock 的 UserQueueService，收到 logLogin 時，回傳假資料
        $mock = \Mockery::mock($abstract, function (MockInterface $mock) use ($closure) {
            $closure($mock);
        });

        // 把 mock 的物件放到容器已解析的實體中
        $this->container->set($abstract, $mock);

        return $mock;
    }
}
