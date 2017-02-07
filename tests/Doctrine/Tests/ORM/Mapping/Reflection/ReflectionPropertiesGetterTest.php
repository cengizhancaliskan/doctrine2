<?php

namespace Doctrine\Tests\ORM\Mapping\Reflection;

use Doctrine\Common\Persistence\Mapping\ReflectionService;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\ORM\Mapping\Reflection\ReflectionPropertiesGetter;
use Doctrine\Tests\DoctrineTestCase;
use Doctrine\Tests\Models\Reflection\ClassWithMixedProperties;
use Doctrine\Tests\Models\Reflection\ParentClass;
use ReflectionClass;

/**
 * Tests for {@see \Doctrine\ORM\Mapping\Reflection\ReflectionPropertiesGetter}
 *
 * @covers \Doctrine\ORM\Mapping\Reflection\ReflectionPropertiesGetter
 */
class ReflectionPropertiesGetterTest extends DoctrineTestCase
{
    public function testRetrievesProperties()
    {
        $properties = (new ReflectionPropertiesGetter(new RuntimeReflectionService()))
            ->getProperties(ClassWithMixedProperties::class);

        self::assertCount(5, $properties);

        foreach ($properties as $property) {
            self::assertInstanceOf('ReflectionProperty', $property);
        }
    }

    public function testRetrievedInstancesAreNotStatic()
    {
        $properties = (new ReflectionPropertiesGetter(new RuntimeReflectionService()))
            ->getProperties(ClassWithMixedProperties::class);

        foreach ($properties as $property) {
            self::assertFalse($property->isStatic());
        }
    }

    public function testExpectedKeys()
    {
        $properties = (new ReflectionPropertiesGetter(new RuntimeReflectionService()))
            ->getProperties(ClassWithMixedProperties::class);

        self::assertArrayHasKey(
            "\0" . ClassWithMixedProperties::class . "\0" . 'privateProperty',
            $properties
        );
        self::assertArrayHasKey(
            "\0" . ClassWithMixedProperties::class . "\0" . 'privatePropertyOverride',
            $properties
        );
        self::assertArrayHasKey(
            "\0" . ParentClass::class . "\0" . 'privatePropertyOverride',
            $properties
        );
        self::assertArrayHasKey(
            "\0*\0protectedProperty",
            $properties
        );
        self::assertArrayHasKey(
            "publicProperty",
            $properties
        );
    }

    public function testPropertiesAreAccessible()
    {
        $object     = new ClassWithMixedProperties();
        $properties = (new ReflectionPropertiesGetter(new RuntimeReflectionService()))
            ->getProperties(ClassWithMixedProperties::class);

        foreach ($properties as $property) {
            self::assertSame($property->getName(), $property->getValue($object));
        }
    }

    public function testPropertyGetterIsIdempotent()
    {
        $getter = (new ReflectionPropertiesGetter(new RuntimeReflectionService()));

        self::assertSame(
            $getter->getProperties(ClassWithMixedProperties::class),
            $getter->getProperties(ClassWithMixedProperties::class)
        );
    }

    public function testPropertyGetterWillSkipPropertiesNotRetrievedByTheRuntimeReflectionService()
    {
        /* @var $reflectionService ReflectionService|\PHPUnit_Framework_MockObject_MockObject */
        $reflectionService = $this->createMock(ReflectionService::class);

        $reflectionService
            ->expects($this->exactly(2))
            ->method('getClass')
            ->with($this->logicalOr(ClassWithMixedProperties::class, ParentClass::class))
            ->will($this->returnValueMap([
                [ClassWithMixedProperties::class, new ReflectionClass(ClassWithMixedProperties::class)],
                [ParentClass::class, new ReflectionClass(ParentClass::class)],
            ]));

        $reflectionService
            ->expects($this->atLeastOnce())
            ->method('getAccessibleProperty');

        $getter = (new ReflectionPropertiesGetter($reflectionService));

        self::assertEmpty($getter->getProperties(ClassWithMixedProperties::class));
    }

    public function testPropertyGetterWillSkipClassesNotRetrievedByTheRuntimeReflectionService()
    {
        /* @var $reflectionService ReflectionService|\PHPUnit_Framework_MockObject_MockObject */
        $reflectionService = $this->createMock(ReflectionService::class);

        $reflectionService
            ->expects($this->once())
            ->method('getClass')
            ->with(ClassWithMixedProperties::class);

        $reflectionService->expects($this->never())->method('getAccessibleProperty');

        $getter = (new ReflectionPropertiesGetter($reflectionService));

        self::assertEmpty($getter->getProperties(ClassWithMixedProperties::class));
    }
}
