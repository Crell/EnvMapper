<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

class EnvWithTypeMismatch
{
    public function __construct(
        // Path is a string, so this should type fail.
        public readonly int $path,
    ) {}
}
