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
        $this->provides = (array) $provides;
    }

    public function provides(): array
    {
        return array_values($this->provides);
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

        if (is_string($entry) && is_instantiable($entry))
        {
            $entry = $container->make($entry);
        }

        $container->setMany(array_fill_keys($this->provides(), $entry));
    }
}
