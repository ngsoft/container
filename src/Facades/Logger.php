<?php

declare(strict_types=1);

namespace NGSOFT\Facades;

use NGSOFT\Container\ContainerInterface;
use NGSOFT\Container\ServiceProvider;
use NGSOFT\Container\SimpleServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logger extends Facade
{
    /**
     * Logs with an arbitrary level.
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public static function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->log($level, $message, $context);
    }

    /**
     * System is unusable.
     */
    public static function emergency(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public static function alert(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example : Application component unavailable, unexpected exception.
     */
    public static function critical(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public static function error(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public static function warning(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->warning($message, $context);
    }

    /**
     * Normal but significant events.
     */
    public static function notice(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public static function info(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->info($message, $context);
    }

    /**
     * Detailed debug information.
     */
    public static function debug(string|\Stringable $message, array $context = []): void
    {
        static::getFacadeRoot()->debug($message, $context);
    }

    protected static function getFacadeAccessor(): string
    {
        return 'LoggerFacade';
    }

    protected static function getServiceProvider(): ServiceProvider
    {
        return new SimpleServiceProvider(
            [self::getFacadeAccessor(), LoggerInterface::class],
            function (ContainerInterface $container)
            {
                if ( ! $container->has(LoggerInterface::class))
                {
                    $container->set(LoggerInterface::class, new NullLogger());
                }
                $logger = $container->get(LoggerInterface::class);

                $container->set(self::getFacadeAccessor(), $logger);
            }
        );
    }
}
