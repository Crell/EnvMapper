<?php

declare(strict_types=1);

namespace Crell\EnvMapper;

class TypeMismatch extends \InvalidArgumentException
{
    public readonly string $class;
    public readonly string $propName;
    public readonly mixed $envValue;

    public static function create(string $class, string $propName, mixed $envValue): self
    {
        $new = new self();
        $new->class = $class;
        $new->propName = $propName;
        $new->envValue = $envValue;

        $valueType = get_debug_type($envValue);

        $propType = (new \ReflectionProperty($class, $propName))->getType()?->getName() ?? 'mixed';

        $new->message = sprintf('Could not read environment variable for "%s" on %s.  A %s was expected but %s provided.', $propName, $class, $propType, $valueType);

        return $new;
    }
}
