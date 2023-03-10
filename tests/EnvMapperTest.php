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
    /** @var array<string, string|int> */
    protected array $source = [
        'PHP_VERSION' => '8.1.14',
        'XDEBUG_MODE' => 'debug',
        'PATH' => 'a value',
        'HOSTNAME' => 'localhost',
        'SHLVL' => 1,
        'ZIP_CODE' => '01234',
    ];

    #[Test]
    public function mapping_different_types_with_defaults_parses_correctly(): void
    {
        $mapper = new EnvMapper();

        /** @var SampleEnvironment $env */
        $env = $mapper->map(SampleEnvironment::class, source: $this->source);

        self::assertNotNull($env->phpVersion);
        self::assertNotNull($env->xdebug_mode);
        self::assertNotNull($env->PATH);
        self::assertNotNull($env->hostname);
        self::assertNotNull($env->shlvl);
        self::assertEquals('default', $env->missing);
        self::assertEquals(false, $env->missingFalse);
        self::assertEquals('', $env->missingEmptyString);
        self::assertNull($env->missingNull);
    }

    #[Test]
    public function undefined_vars_stay_undefined_if_not_strict(): void
    {
        $mapper = new EnvMapper();

        /** @var SampleEnvironment $env */
        $env = $mapper->map(EnvWithMissingValue::class, source: $this->source);

        self::assertFalse((new \ReflectionClass($env))->getProperty('missing')->isInitialized($env));
    }

    #[Test]
    public function undefined_vars_throws_if_strict(): void
    {
        $this->expectException(MissingEnvValue::class);
        $this->expectExceptionMessage('No matching environment variable found for property "missing" of class Crell\EnvMapper\Envs\EnvWithMissingValue.');

        $mapper = new EnvMapper();

        /** @var SampleEnvironment $env */
        $env = $mapper->map(EnvWithMissingValue::class, requireValues: true, source: $this->source);
    }

    #[Test]
    public function type_mismatch(): void
    {
        $this->expectException(TypeMismatch::class);
        $this->expectExceptionMessage('Could not read environment variable for "path" on Crell\\EnvMapper\\Envs\\EnvWithTypeMismatch.  A int was expected but string provided.');

        $mapper = new EnvMapper();

        /** @var EnvWithTypeMismatch $env */
        $env = $mapper->map(EnvWithTypeMismatch::class, source: $this->source);

        $this->fail('Exception was not thrown.');
    }
}
