<?php

declare(strict_types=1);

namespace Crell\EnvMapper;

use Crell\EnvMapper\Envs\EnvWithMissingValue;
use Crell\EnvMapper\Envs\EnvWithTypeMismatch;
use Crell\EnvMapper\Envs\SampleEnvironment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnvMapperTest extends TestCase
{
    #[Test]
    public function mapping_different_types_with_defaults_parses_correctly(): void
    {
        $mapper = new EnvMapper();

        /** @var SampleEnvironment $env */
        $env = $mapper->map(SampleEnvironment::class, $_ENV);

        self::assertNotNull($env->phpVersion);
        self::assertNotNull($env->xdebug_mode);
        self::assertNotNull($env->PATH);
        self::assertNotNull($env->hostname);
        self::assertEquals('default', $env->missing);
    }

    #[Test]
    public function undefined_vars_stay_undefined(): void
    {
        $mapper = new EnvMapper();

        /** @var SampleEnvironment $env */
        $env = $mapper->map(EnvWithMissingValue::class, $_ENV);

        self::assertFalse((new \ReflectionClass($env))->getProperty('missing')->isInitialized($env));
    }

    #[Test]
    public function type_mismatch(): void
    {
        $this->expectException(TypeMismatch::class);
        $this->expectExceptionMessage('Could not read environment variable for "path" on Crell\\EnvMapper\\Envs\\EnvWithTypeMismatch.  A int was expected but string provided.');

        $mapper = new EnvMapper();

        print "About to map.\n";

        /** @var EnvWithTypeMismatch $env */
        $env = $mapper->map(EnvWithTypeMismatch::class, $_ENV);
        self::assertNotNull($env->path);

        print "Mapped.\n";

        $this->fail('Exception was not thrown.');
    }
}
