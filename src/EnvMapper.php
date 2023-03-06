<?php

declare(strict_types=1);

namespace Crell\EnvMapper;

class EnvMapper
{
    /**
     * @var array<class-string, array<\ReflectionParameter>>
     */
    private array $constructorParameterList = [];

    /**
     *
     *
     * @param class-string $class
     * @param array<string, mixed>|null $envArray
     * @return object
     */
    public function map(string $class, ?array $envArray = null): object
    {
        $envArray ??= $_ENV;

        $rClass = new \ReflectionClass($class);

        $rProperties = $rClass->getProperties();

        $toSet = [];
        foreach ($rProperties as $rProp) {
            $propName = $rProp->getName();
            $envName = $this->normalizeName($propName);
            if (isset($envArray[$envName])) {
                $toSet[$propName] = $this->typeNormalize($envArray[$envName]);
            } elseif ($defaultValue = $this->getDefaultValueFromConstructor($rProp)) {
                $toSet[$propName] = $defaultValue;
            }
        }

        $populator = function (array $props) {
            var_dump($props);
            foreach ($props as $k => $v) {
                try {
                    $this->$k = $v;
                } catch (\TypeError $e) {
                    throw TypeMismatch::create($this::class, $k, $v);
                }
            }
        };

        $env = $rClass->newInstanceWithoutConstructor();

        $populator->call($env, $toSet);

        return $env;
    }

    /**
     * Normalizes a scalar value to its most-restrictive type.
     *
     * Env values are always imported as strings, but if we want to
     * push them into well-typed numeric fields we need to cast them
     * appropriately.
     *
     * @param int|float|string $val
     *   The value to normalize.
     * @return int|float|string
     *   The passed value, but now with the correct type.
     */
    private function typeNormalize(int|float|string $val): int|float|string
    {
        if (!is_numeric($val)) {
            return $val;
        }

        // It's either a float or an int, but floor() wants a float.
        $val = (float) $val;

        if (floor($val) === $val) {
            return (int) $val;
        }
        return $val;
    }

    /**
     * This is actually rather slow.  Reflection's performance cost hurts here.
     *
     * @param \ReflectionProperty $subject
     * @return mixed
     */
    protected function getDefaultValueFromConstructor(\ReflectionProperty $subject): mixed
    {
        $params = $this->getPropertiesForClass($subject->getDeclaringClass());

        $param = $params[$subject->getName()] ?? null;

        return $param?->isDefaultValueAvailable()
            ? $param->getDefaultValue()
            : null;
    }

    /**
     * @return array<string, \ReflectionParameter>
     */
    protected function getPropertiesForClass(\ReflectionClass $rClass): array
    {
        return $this->constructorParameterList[$rClass->getName()] ??= $this->makePropertiesForClass($rClass);
    }

    /**
     * @return array<string, \ReflectionParameter>
     */
    protected function makePropertiesForClass(\ReflectionClass $rClass): array
    {
        $props = [];
        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rProp) {
            $props[$rProp->getName()] = $rProp;
        }
        return $props;
    }

    /**
     * Normalizes a string to UPPER_CASE, as that's what env vars almost always use.
     */
    protected function normalizeName(string $input): string
    {
        $words = preg_split(
            '/(^[^A-Z]+|[A-Z][^A-Z]+)/',
            $input,
            -1, /* no limit for replacement count */
            PREG_SPLIT_NO_EMPTY /* don't return empty elements */
            | PREG_SPLIT_DELIM_CAPTURE /* don't strip anything from output array */
        );

        if (!$words) {
            // I don't know how this is even possible.
            throw new \RuntimeException('Could not normalize name: ' . $input);
        }

        return \implode('_', array_map(strtoupper(...), $words));
    }
}
