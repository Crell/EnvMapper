<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

enum StringBackedEnum: string {
    case Foo = 'FOO';
    case Bar = 'BAR';
    case Baz = 'BAZ';
}
