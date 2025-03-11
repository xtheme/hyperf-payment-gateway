<?php

declare(strict_types=1);

namespace App\Facade;

abstract class Facade
{
    // 容器实例

    public static function __callStatic($method, $args)
    {
        $instance = static::instance();

        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }

    // 单例模式

    public static function instance()
    {
        return static::singleton() ?
            static::container()->get(static::getFacadeAccessor()) :
            static::container()->make(static::getFacadeAccessor(), static::getResolveAccessor());
    }

    // 获取实例

    public static function container()
    {
        return \Hyperf\Context\ApplicationContext::getContainer();
    }

    // 门面
    protected static function getFacadeAccessor()
    {
        throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    // make 参数

    protected static function singleton()
    {
        return true;
    }

    // 静态访问

    protected static function getResolveAccessor()
    {
        return [];
    }
}
