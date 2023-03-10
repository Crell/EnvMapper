# EnvMapper

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

Reading environment variables is a common part of most applications.  However, it's often done in an ad-hoc and unsafe
way, by calling `getenv()` or reading `$_ENV` from an arbitrary place in code.  That means error handling, missing-value
handling, default values, etc. are scattered about the code base.

This library changes that.  It allows you to map environment variables into arbitrary classed objects extremely fast,
which allows using the class definition itself for default handling, type safety, etc.  That class can then be registered
in your dependency injection container to become automatically available to any service.

## Usage

EnvMapper has almost no configuration.  Everything is just the class.

```php
// Define the class.
class DbSettings
{
    public function __construct(
        // Loads the DB_USER env var.
        public readonly string $dbUser,
        // Loads the DB_PASS env var.
        public readonly string $dbPass,
        // Loads the DB_HOST env var.
        public readonly string $dbHost,
        // Loads the DB_PORT env var.
        public readonly int $dbPort,
        // Loads the DB_NAME env var.
        public readonly string $dbName,
    ) {}
}

$mapper = new Crell\EnvMapper\EnvMapper();

$db = $mapper->map(DbSettings::class);
```

`$db` is now an instance of `DbSettings` with all five properties populated from the environment, if defined. That object
may now be used anywhere, passed into a constructor, or whatever.  Because its properties are all `readonly`, you
can rest assured that no service using this object will be able to modify it.

You can use any class you'd like, however.  All that matters is the defined properties (defined via constructor promotion
or not).  The properties may be whatever visibility you like, and you may include whatever methods you'd like.

### Name mapping and default values

EnvMapper will convert the name of the property into `UPPER_CASE` style, which is the style typically used
for environment variables.  It will then look for an environment variable by that name and assign it to that property.
That means you may use `lowerCamel` or `snake_case` for object properties.  Both will work fine.

If no environment variable is found, but the property has a default value set in the class's constructor, that default
value will be used.  (Default values on non-promoted properties are not checked, on the assumption that they will be
`readonly` properties, and readonly properties cannot have default values.)  If there is no default value, it will be
left uninitialized.

Alternatively, you may set `requireValues: true` in the `map()` call.  If `requireValues` is set, then a missing property will instead
throw a `MissingEnvValue` exception.

```php
class MissingStuff
{
    public function __construct(
        public readonly string $notSet,
    ) {}
}

// This will throw a MissingEnvValue exception unless there is a NOT_SET env var defined.
$mapper->map(MissingStuff::class, requireValues: true);
```

### Type enforcement

Environment variables are always strings, but you may know out-of-band that they are supposed to be an `int` or `float`.
EnvMapper will automatically cast int-like values (like "5" or "42") to integers, and float-like values (like "3.14") to
floats, so that they will safely assign to the typed properties on the object.

If a property is typed `bool`, then the values "1", "true", "yes", and "on" (in any case) will evaluate to `true`. Anything
else will evaluate to false.

If a value cannot be assigned (for instance, if the `DB_PORT` environment variable was set to `"any"`), then a `TypeMismatch`
exception will be thrown.

### dot-env compatibility

EnvMapper reads values from `$_ENV` by default.  If you are using a library that reads `.env` files into the environment,
it should work fine with EnvMapper provided it populates `$_ENV`.  EnvMapper does not use `getenv()` as it is much slower.

## Common patterns

### Registering with a DI Container

The recommended way to use `EnvMapper` is to wire it into your Dependency Injection Container, preferably one that
supports auto-wiring.  For example, in a Laravel Service Provider you could do this:

```php
namespace App\Providers;
 
use App\Environment;
use Crell\EnvMapper\EnvMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
 
class EnvMapperServiceProvider extends ServiceProvider
{
    // The EnvMapper has no constructor arguments, so registering it is simple.
    public $singletons = [
        EnvMapper::class => EnvMapper::class,
    ];
    
    public function register(): void
    {
        // When the Environment class is requested, it will be loaded lazily out of the env vars by the mapper.
        // Because it's a singleton, the object will be automatically cached.
        $this->app->singleton(Environment::class, fn(Application $app) => $app[EnvMapper::class]->map(Environment::class));
    }
}
```

In Symfony, you could implement the same configuration in `services.yaml`:

```yaml
services:
    Crell\EnvMapper\EnvMapper: ~

    App\Environment:
      factory:   ['@Crell\EnvMapper\EnvMapper', 'map']
      arguments: ['App\Environment']
```

Now, any service may simply declare a constructor argument of type `Environment` and the container will automatically 
instantiate and inject the object as needed.

### Testing

The key reason to use a central environment variable mapper is to make testing easier.  Reading the environment directly
from each service is a global dependency, which makes testing more difficult.  Instead, making a dedicated environment
class an injectable service (as in the example above) means any service that uses it may trivially be passed a manually
created version.

```php
class AService
{
    public function __construct(private readonly AwsSettings $settings) {}
    
    // ...
}

class AServiceTest extends TestCase
{
    public function testSomething(): void
        $awsSettings = new AwsSettings(awsKey: 'fake', awsSecret: 'fake');

        $s = new Something($awsSettings);

        // ...
    }
}
```

### Multiple environment objects

Any environment variables that are set but not present in the specified class will be ignored.  That means it's trivially
easy to load different variables into different classes.  For example:

```php
class DbSettings
{
    public function __construct(
        public readonly string $dbUser,
        public readonly string $dbPass,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
    ) {}
}

class AwsSettings
{
    public function __construct(
        public readonly string $awsKey,
        public readonly string $awsSecret,
    ) {}
}
```

```php
// Laravel version.
class EnvMapperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DbSettings::class, fn(Application $app) => $app[EnvMapper::class]->map(DbSettings::class));
        $this->app->singleton(AwsSettings::class, fn(Application $app) => $app[EnvMapper::class]->map(AwsSettings::class));
    }
}
```

```yaml
# Symfony version
services:
  Crell\EnvMapper\EnvMapper: ~

  App\DbSettings:
    factory:   ['@Crell\EnvMapper\EnvMapper', 'map']
    arguments: ['App\DbSettings']

  App\AwsSettings:
    factory:   ['@Crell\EnvMapper\EnvMapper', 'map']
    arguments: ['App\AwsSettings']
```

## Advanced usage

EnvMapper is designed to be lightweight and fast.  For that reason, its feature set is deliberately limited.

However, there are cases you may wish to have a more complex environment setup.  For instance, you may want to
rename properties more freely, nest related properties inside sub-objects, or map comma-delimited environment variables
into an array.  EnvMapper is not designed to handle that.

However, its sibling project [`Crell/Serde`](https://www.github.com/Crell/Serde) can do so easily.  Serde is a general
purpose serialization library, but you can easily feed it `$_ENV` as an array to deserialize from into an object. That
gives you access to all of Serde's capability to rename, collect, nest, and otherwise translate data as it's being
deserialized into an object.  The basic workflow is the same, and registration in your service container is nearly
identical.

```php
$serde = new SerdeCommon();

$env = $serde->deserialize($_ENV, from: 'array', to: Environment::class);
```

Using Serde will be somewhat slower than using EnvMapper, but both are still very fast and suitable for almost any application.

See the Serde documentation for all of its various options.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email larry at garfieldtech dot com instead of using the issue tracker.

## Credits

- [Larry Garfield][link-author]
- [All Contributors][link-contributors]

## License

The Lesser GPL version 3 or later. Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/Crell/EnvMapper.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/License-LGPLv3-green.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/Crell/EnvMapper.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/Crell/EnvMapper
[link-scrutinizer]: https://scrutinizer-ci.com/g/Crell/EnvMapper/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/Crell/EnvMapper
[link-downloads]: https://packagist.org/packages/Crell/EnvMapper
[link-author]: https://github.com/Crell
[link-contributors]: ../../contributors
