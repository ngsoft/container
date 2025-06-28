<?php

declare(strict_types=1);

namespace NGSOFT\Container;

class SimpleServiceProvider implements ServiceProvider
{
    protected array $provides = [];

    public function __construct(
        array|string $provides,
        protected mixed $register
    ) {
        if ( ! is_array($provides))
        {
            $provides = [$provides];
        }
        $this->provides = $provides;
    }

    public function provides(): array
    {
        return array_values(array_unique($this->provides));
    }

    public function register(ContainerInterface $container): void
    {
        $entry = $this->register;

        if (is_null($entry) || ! count($this->provides()))
        {
            return;
        }

        if ($entry instanceof \Closure)
        {
            $entry($container);
            return;
        }

        if (is_string($entry) && Utils::isInstantiable($entry))
        {
            $entry = $container->make($entry);
        }

        $container->setMany(array_fill_keys($this->provides(), $entry));
    }
}
