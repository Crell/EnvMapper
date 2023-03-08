<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

class SampleEnvironment
{
    public function __construct(
        // lowerCamel casing.
        public readonly string $phpVersion,
        // snake_case casing.
        public readonly string $xdebug_mode,
        // CAPITAL casing.
        public readonly string $PATH,
        public readonly string $hostname,
        // Verify an int can be read correctly.
        public readonly int $shlvl,
        // This is a numeric string, but should stay a string.
        public readonly string $zipCode,
        // This is not defined in the environment, so the default value should be used.
        public readonly string $missing = 'default',
    ) {}
}
