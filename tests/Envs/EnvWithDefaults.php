<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

class EnvWithDefaults
{
    public string $propDefault = 'beep';

    public string $basic;

    public function __construct(public readonly string $promotedDefault = 'boop', string $basic = 'narf')
    {
        $this->basic = $basic;
    }
}
