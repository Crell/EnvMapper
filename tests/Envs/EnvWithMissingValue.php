<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

class EnvWithMissingValue
{
    public function __construct(
        public readonly string $missing,
    ) {}
}
