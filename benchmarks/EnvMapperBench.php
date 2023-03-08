<?php

declare(strict_types=1);

namespace Crell\EnvMapper\Benchmarks;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\EnvBench\Environment;
use Crell\EnvBench\EnvironmentNoFolding;
use Crell\EnvBench\EnvironmentPhpNames;
use Crell\EnvBench\ManualMap;
use Crell\EnvMapper\EnvMapper;
use Crell\EnvMapper\Envs\SampleEnvironment;
use Crell\Serde\Formatter\ArrayFormatter;
use Crell\Serde\SerdeCommon;
use PhpBench\Benchmark\Metadata\Annotations\AfterMethods;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\OutputTimeUnit;
use PhpBench\Benchmark\Metadata\Annotations\RetryThreshold;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * @Revs(100)
 * @Iterations(10)
 * @Warmup(2)
 * @BeforeMethods({"setUp"})
 * @AfterMethods({"tearDown"})
 * @OutputTimeUnit("milliseconds", precision=4)
 * @RetryThreshold(15.0)
 */
class EnvMapperBench
{
    protected readonly EnvMapper $envMapper;

    /** @var array<string, string|int> */
    protected array $source = [
        'PHP_VERSION' => '8.1.14',
        'XDEBUG_MODE' => 'debug',
        'PATH' => 'a value',
        'HOSTNAME' => 'localhost',
        'SHLVL' => 1,
    ];

    public function setUp(): void
    {
        $this->envMapper = new EnvMapper();
    }

    public function tearDown(): void {}

    public function bench_envmapper(): void
    {
        /** @var SampleEnvironment $env */
        $env = $this->envMapper->map(SampleEnvironment::class, source: $this->source);
    }

}
