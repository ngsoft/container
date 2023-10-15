<?php

declare(strict_types=1);

namespace NGSOFT\Container;

interface ServiceProvider
{
    /**
     * Register the service into the container.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides(): array;
}
