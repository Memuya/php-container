<?php declare(strict_types=1);

use Memuya\Container;
use Memuya\ContainerException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerTest extends TestCase
{
    public function testCanBindObjectToContainer(): void
    {
        $object = new \stdClass;
        $container = new Container;
        $container->bind('obj', fn () => new \stdClass);

        // Retrieving a bound object should create a new instnce everytime so calling
        // ->get() twice on the same ID should result in different object instances.
        $this->assertNotSame($container->get('obj'), $container->get('obj'));

        $this->assertFalse($container->isSingleton('obj'));
        $this->assertSame($object::class, $container->get('obj')::class);
    }

    public function testCanBindPrimivitesToContainer(): void
    {
        $string = 'string';
        $int = 1;
        $bool = true;

        $container = new Container;
        $container->bind('string', fn () => $string);
        $container->bind('int', fn () => $int);
        $container->bind('bool', fn () => $bool);

        $this->assertSame($string, $container->get('string'));
        $this->assertSame($int, $container->get('int'));
        $this->assertSame($bool, $container->get('bool'));
    }

    public function testCanRemoveBindingFromContainer()
    {
        $container = new Container;
        $container->bind('id', fn () => 'test');

        $this->assertSame('test', $container->get('id'));

        $container->remove('id');

        $this->assertFalse($container->has('id'));
    }

    public function testCanMakeFreshObjects()
    {
        $objectToBuild = new class {
            public string $prop = 'default';
        };

        $container = new Container;
        $builtObject = $container->make($objectToBuild::class);

        $this->assertSame($objectToBuild::class, $builtObject::class);
        $this->assertSame($objectToBuild->prop, $builtObject->prop);
    }

    public function testCanMakeFreshObjectsAndPassArgumentsToIt()
    {
        $objectToBuild = new class {
            public function __construct(public string $prop = 'default') {}
        };

        $container = new Container;
        $builtObject = $container->make(
            $objectToBuild::class,
            ['prop' => 'override']
        );

        $this->assertSame($objectToBuild::class, $builtObject::class);
        $this->assertSame('override', $builtObject->prop);
    }

    public function testCreatedObjectsPerfersBindedValueOverDefaultParameterValue()
    {
        $objectToBuild = new class {
            public string $prop;

            public function __construct(string $prop = 'default')
            {
                $this->prop = $prop;
            }
        };
        
        $container = new Container;
        $container->bind('prop', fn () => 'test');
        $builtObject = $container->make($objectToBuild::class);

        $this->assertSame($objectToBuild->prop, 'default');
        $this->assertSame($builtObject->prop, 'test');
    }

    public function testThrowsNotFoundExceptionWhenBindingNotFound()
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $container = new Container;
        $container->get('unknown');
    }

    public function testContainerCanBeUsedAsArray()
    {
        $container = new Container;
        $container['id'] = 'test';

        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertSame($container['id'], 'test');
        $this->assertTrue(isset($container['id']));

        unset($container['id']);

        $this->assertFalse(isset($container['id']));
    }

    public function testCanBindAndResolveSingletonsFromContainer()
    {
        $container = new Container;
        $container->singleton('test', fn () => new \stdClass);
        
        $this->assertTrue($container->isSingleton('test'));
        $this->assertSame($container->get('test'), $container->get('test'));
    }
}
