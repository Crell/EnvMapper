<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Envs;

enum IntegerBackedEnum: int {
    case Foo = 1;
    case Bar = 2;
    case Baz = 3;
}
