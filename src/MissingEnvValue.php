<?php

declare(strict_types=1);

namespace Crell\EnvMapper;

class MissingEnvValue extends \InvalidArgumentException
{
    public readonly string $propName;
    public readonly string $class;

    public static function create(string $propName, string $class): self
    {
        $new = new self();
        $new->propName = $propName;
        $new->class = $class;

        $new->message = sprintf('No matching environment variable found for property "%s" of class %s.', $propName, $class);

        return $new;
    }
}
