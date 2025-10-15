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
     * Maps environment variables to the specified class.
     *
     * @template T of object
     * @param class-string<T> $class
     *   The class to which to map values.
     * @param bool $requireValues
     *   If true, any unmatched properties will result in an exception.  If false, unmatched properties
     *   will be ignored, which in most cases means they will be uninitialized.
     * @param array<string, mixed>|null $source
     *   The array to map from.  If not specified, $_ENV will be used. Note that because the
     *   primary use case is environment variables, the input array MUST have keys that are UPPER_CASE
     *   strings.
     * @return T
     */
    public function map(string $class, bool $requireValues = false, ?array $source = null): object
    {
        $source ??= $_ENV;

        $rClass = new \ReflectionClass($class);

        $rProperties = $rClass->getProperties();

        $toSet = [];
        foreach ($rProperties as $rProp) {
            $propName = $rProp->getName();
            $envName = $this->normalizeName($propName);
            if (isset($source[$envName])) {
                $toSet[$propName] = $this->typeNormalize($source[$envName], $rProp);
            } elseif (PropValue::None !== $default = $this->getDefaultValue($rProp)) {
                $toSet[$propName] = $default;
            } elseif ($requireValues) {
                throw MissingEnvValue::create($propName, $class);
            }
        }

        $populator = function (array $props) {
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
     * @param string $val
     *   The value to normalize.
     * @return int|float|string|bool
     *   The passed value, but now with the correct type.
     */
    private function typeNormalize(string $val, \ReflectionProperty $rProp): int|float|string|bool|\BackedEnum
    {
        $rType = $rProp->getType();
        if ($rType instanceof \ReflectionNamedType) {
            $name = $rType->getName();

            if (is_a($name, \BackedEnum::class, true)) {
                $rEnum = new \ReflectionEnum($name);
                $backingType = $rEnum->getBackingType();
                assert($backingType instanceof \ReflectionNamedType);
                $isIntBacked = $backingType->getName() === 'int';

                return $name::from($isIntBacked ? (int) $val : $val);
            }

            return match ($name) {
                'string' => $val,
                'float' => is_numeric($val)
                    ? (float) $val
                    : throw TypeMismatch::create($rProp->getDeclaringClass()->getName(), $rProp->getName(), $val),
                'int' => (is_numeric($val) && floor((float) $val) === (float) $val)
                    ? (int) $val
                    : throw TypeMismatch::create($rProp->getDeclaringClass()->getName(), $rProp->getName(), $val),
                'bool' => in_array(strtolower($val), [1, '1', 'true', 'yes', 'on'], false),
                default => throw TypeMismatch::create($rProp->getDeclaringClass()->getName(), $rProp->getName(), $val),
            };
        }

        throw new \RuntimeException('Compound types are not yet supported');
    }

    /**
     * This is actually rather slow.  Reflection's performance cost hurts here.
     *
     * We only care about defaults from the constructor; if a non-readonly property
     * has a default value, then newInstanceWithoutConstructor() will use it for us
     * and we don't need to do anything.
     *
     * @param \ReflectionProperty $subject
     * @return mixed
     */
    protected function getDefaultValue(\ReflectionProperty $subject): mixed
    {
        $params = $this->getConstructorArgs($subject->getDeclaringClass());

        $param = $params[$subject->getName()] ?? null;

        return $param?->isDefaultValueAvailable()
            ? $param->getDefaultValue()
            : PropValue::None;
    }

    /**
     * @return array<string, \ReflectionParameter>
     */
    protected function getConstructorArgs(\ReflectionClass $rClass): array
    {
        return $this->constructorParameterList[$rClass->getName()] ??= $this->makeConstructorArgs($rClass);
    }

    /**
     * @return array<string, \ReflectionParameter>
     */
    protected function makeConstructorArgs(\ReflectionClass $rClass): array
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
