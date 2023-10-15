<?php

declare(strict_types=1);

namespace NGSOFT\Facades\Facade;

use NGSOFT\Container\ContainerInterface;
use NGSOFT\Container\ServiceProvider;
use NGSOFT\Facades\Facade;

final class InnerFacade extends Facade
{
    protected array $resolvedInstances       = [];
    protected ?ContainerInterface $container = null;
    private array $providers                 = [];

    /**
     * Starts the container.
     */
    final public function boot(array $definitions = []): void
    {
        if ( ! $this->container)
        {
            $this->getContainer()->setMany($definitions);
        }
    }

    final public function registerServiceProvider(string $accessor, ServiceProvider $provider): void
    {
        if ( ! isset($this->providers[$accessor]))
        {
            $this->providers[$accessor] = $provider;
            $this->getContainer()->register($provider);
        }
    }

    final public function getResolvedInstance(string $name): mixed
    {
        return $this->resolvedInstances[$name] ?? null;
    }

    final public function setResolvedInstance(string $name, object $instance): void
    {
        $this->resolvedInstances[$name] = $instance;
    }

    final public function getContainer(): ContainerInterface
    {
        return $this->container ??= $this->getNewContainer();
    }

    final public function setContainer(ContainerInterface $container): void
    {
        $this->container = $this->registerFacades($container);
    }

    protected function getNewContainer(): ContainerInterface
    {
        $class = self::DEFAULT_CONTAINER_CLASS;
        return $this->registerFacades(new $class());
    }

    protected static function getFacadeAccessor(): string
    {
        return 'Facade';
    }

    private function registerFacades(ContainerInterface $container): ContainerInterface
    {
        if (empty($this->providers))
        {
            foreach (scandir($dir = dirname(__DIR__)) ?: [] as $file)
            {
                if (str_ends_with($file, '.php'))
                {
                    require_once $dir . DIRECTORY_SEPARATOR . $file;
                }
            }

            foreach (\implements_class(Facade::class, false) as $class)
            {
                if (__CLASS__ === $class || Facade::class === $class)
                {
                    continue;
                }
                $accessor                   = $class::getFacadeAccessor();

                $this->providers[$accessor] = $class::getServiceProvider();
            }
        }

        foreach ($this->providers as $provider)
        {
            $container->register($provider);
        }

        return $container;
    }
}
