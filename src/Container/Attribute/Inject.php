<?php

declare(strict_types=1);

namespace NGSOFT\Container\Attribute;

use NGSOFT\Container\Exceptions\ContainerError;
use NGSOFT\Container\Utils;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER)]
final class Inject implements \Stringable
{
    public string $name      = '';
    public array $parameters = [];

    public function __construct(
        array|string $name = ''
    ) {
        if ( ! is_string($name))
        {
            foreach ($name as $index => $id)
            {
                if ( ! is_string($id))
                {
                    throw new ContainerError(
                        sprintf(
                            "#[Inject([%s => 'id'])] expects 'id' to be a string, %s given.",
                            var_export($index, true),
                            \get_debug_type($id)
                        )
                    );
                }

                $this->parameters[$index] = $id;
            }
        } else
        {
            $this->name = $name;
        }
    }

    public function __toString(): string
    {
        $param = "'{$this->name}'";

        if (count($this->parameters))
        {
            $param = '[';

            foreach ($this->parameters as $index => $id)
            {
                $param .= sprintf("'%s'=>'%s',", (string) $index, $id);
            }
            $param .= ']';
        }
        return sprintf('#[%s(%s)]', Utils::classBasename(self::class), $param);
    }
}
