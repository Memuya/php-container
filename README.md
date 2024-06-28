# PHP Dependency Injection Container
A simple and lightweight dependency injection container built in PHP, compliant with [PSR-11](https://www.php-fig.org/psr/psr-11/) standards. This container is designed for binding values and creating objects, automatically resolving any dependencies they require.

# Usage
## Initialise the container
First create an instance of the container.
```php
<?php

use Memuya\Container\Container;

$container = new Container();
```

You can also create a new instance and retrieve it via:
```php
<?php
use Memuya\Container\Container;

$container = Container::getInstance();
```

## Bind to container
You can bind a key to both simple and complex values to the container.
```php
<?php
// Scalar/primitive type.
$container->bind('key', fn (): string => 'Hello, world!');

// Object.
$container->bind('someObject', function (): \stdClass {
  $object = new \stdClass();
  $object->name = 'Bob';

  return $object;
});
```

## Check if binding exist
To see if a binding already exists in the container, you can use the `has()` method.
```php
$container->has('key'); // true
$container->has('doesNotExist'); // false
```

## Retrieve value from container

```php
<?php

$key = $container->get('key');
$someObject = $container->get('someObject');
```

If a key does not exists, the container will throw a `NotFoundException` exception. This exception implements the `Psr\Container\NotFoundExceptionInterface` interface.
```php
<?php

use Memuya\Container\Exceptions\NotFoundException;

try {
  $key = $container->get('doesNotExist');
} catch (NotFoundException $e) {
  echo $ex->getMessage(); // 'doesNotExist' not found in container
}
```

Each time an object is retrieved from the container, it will re-create it fresh. If you require the object be binded as a singleton, you can use the `singleton()` method inplace of `bind()`.
```php
$container->singleton('someObject', function (): \stdClass {
  $object = new \stdClass();
  $object->name = 'Bob';

  return $object;
});
```
This will give you back the exact same object every time as it is resolved when the binding is initiated.

You can then check if a binding is a singleton with the `isSingleton()` method.
```php
$container->isSingleton('key'); // false
$container->isSingleton('someObject'); // true
```

## Alias
If required, you can alias an already existing key in the container to another key.
```php
$container->alias('key', 'aliasKey');

$container->get('key'); // Hello, world
$container->get('aliasKey'); // Hello, world
```

## Removing binding
If you no longer require a binding in the container, you may remove it with the `remove()` method.
```php
$container->remove('key');
$container->get('key'); // Throws a NotFoundException exception.
```

# Creating objects and resolving their dependencies
The container can also be used to create and resolve objects and their dependencies automatically. If a class or its dependencies cannot be resolved, a `ContainerException` will be thrown.

Take the below as an example, where we have class `A` which has class `B` as a dependency.
```php
<?php

class A
{
  public function __construct(private B $b) {}

  public function sayHello(): string
  {
    return $this->b->greet();
  }
}

class B
{
  public function greet(): string
  {
    return 'Hello!';
  }
}
```

You can leverage the container to built class A and resolve it's dependency `B`.
```php

use Memuya\Container\Exceptions\ContainerException;

try {
  $a = $container->make(A::class);
  $a->sayHello(); // Hello!
} catch (ContainerException $ex) {

}
```

