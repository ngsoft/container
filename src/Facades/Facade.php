<?php

declare(strict_types=1);

namespace NGSOFT\Facades;

use NGSOFT\Container\Container;
use NGSOFT\Container\NullServiceProvider;
use NGSOFT\Container\ServiceProvider;
use NGSOFT\Facades\Facade\InnerFacade;

abstract class Facade
{
    protected const DEFAULT_CONTAINER_CLASS = Container::class;

    private static ?Facade $innerFacade     = null;

    private function __construct() {}

    /**
     * Handle dynamic, static calls to the object.
     */
    final public static function __callStatic(string $name, array $arguments): mixed
    {
        $instance = static::getFacadeRoot();

        if ( ! $instance)
        {
            throw new \RuntimeException('A facade root has not been set.');
        }

        if ( ! method_exists($instance, $name))
        {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        return $instance->{$name}(...$arguments);
    }

    // //////////////////////////   Utilities   ////////////////////////////

    /**
     * Get the root object behind the facade.
     */
    final public static function getFacadeRoot(): mixed
    {
        if (__CLASS__ === static::class)
        {
            return self::getInnerFacade();
        }

        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    // //////////////////////////   Overrides   ////////////////////////////

    /**
     * Get the registered name of the component.
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Get the service provider for the component.
     */
    protected static function getServiceProvider(): ServiceProvider
    {
        return new NullServiceProvider();
    }

    /**
     * Indicates if the resolved instance should be cached.
     */
    protected static function isCached(): bool
    {
        return true;
    }

    /**
     * Returns the class basename of the facade.
     */
    protected static function getAlias(): string
    {
        return \class_basename(static::class);
    }

    /**
     * Get The facade instance.
     */
    final protected static function getInnerFacade(): InnerFacade
    {
        /*
         * we extend the facade as it is abstract
         * with that we can Facade::setContainer() without static error
         */
        return self::$innerFacade ??= new InnerFacade();
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @phan-suppress PhanUndeclaredMethod
     */
    final protected static function resolveFacadeInstance(string $name): mixed
    {
        $facade   = self::getInnerFacade();

        $resolved = $facade->getResolvedInstance($name);

        if ( ! is_null($resolved))
        {
            return $resolved;
        }

        $facade->registerServiceProvider($name, static::getServiceProvider());

        if ($resolved = $facade->getContainer()->get($name))
        {
            static::isCached() && $facade->setResolvedInstance($name, $resolved);
        }

        return $resolved;
    }
}
