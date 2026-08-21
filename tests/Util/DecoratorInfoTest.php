<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\FinalServiceExtendingParent;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\FinalServiceWithoutParent;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\OtherServiceInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceImplementingInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceImplementingTwoInterfaces;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceOverridingParentMethod;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceWithExtraMethods;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceWithoutInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceWithVariadicMethod;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\Sub\ServiceImplementingInterface as SubServiceImplementingInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\Sub\ServiceOverridingParentMethod as SubServiceOverridingParentMethod;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\Sub\ServiceWithExtraMethods as SubServiceWithExtraMethods;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Bundle\MakerBundle\Util\DecoratorInfo;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

class DecoratorInfoTest extends TestCase
{
    public function testInvalid()
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('Cannot decorate "Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\FinalServiceWithoutParent", its class does not have any interface, parent class and its final.');
        new DecoratorInfo('FooBar', 'foo.bar', FinalServiceWithoutParent::class);
    }

    /** @dataProvider publicMethodsProvider */
    public function testGetPublicMethods(string $decoratedClassOrInterface, array $expected)
    {
        $decoratorInfo = new DecoratorInfo('FooBar', 'foo.bar', $decoratedClassOrInterface);

        $this->assertSame($expected, array_keys($decoratorInfo->getPublicMethods()));
    }

    public function publicMethodsProvider(): \Generator
    {
        yield [ServiceInterface::class, ['getName', 'getDefault']];
        yield [ServiceImplementingInterface::class, ['getName', 'getDefault']];
        yield [ServiceWithExtraMethods::class, ['getName', 'getFoo', 'getStaticValue', 'getDefault']];
        yield [ServiceWithoutInterface::class, ['getFoo']];
        yield [ServiceOverridingParentMethod::class, ['getName', 'getDefault']];
        yield [FinalServiceExtendingParent::class, ['getName', 'getDefault']];
        yield [ServiceImplementingTwoInterfaces::class, ['getName', 'getDefault']];
    }

    /** @dataProvider classDataProvider */
    public function testGetClassData(string $decoratedId, string $decoratedClassOrInterface, ClassData $expected)
    {
        $decoratorInfo = new DecoratorInfo('FooBar', $decoratedId, $decoratedClassOrInterface);

        $this->assertEquals($expected, $decoratorInfo->getClassData());
    }

    public function classDataProvider(): \Generator
    {
        yield [
            'foo.bar',
            ServiceInterface::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [ServiceInterface::class],
            ),
        ];

        yield [
            'foo.bar',
            ServiceImplementingInterface::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [ServiceInterface::class],
            ),
        ];

        yield [
            'foo.bar',
            ServiceWithExtraMethods::class,
            ClassData::create(
                class: 'FooBar',
                extendsClass: ServiceWithExtraMethods::class,
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                    ServiceWithExtraMethods::class,
                    ServiceInterface::class,
                ],
            ),
        ];

        yield [
            'foo.bar',
            ServiceWithoutInterface::class,
            ClassData::create(
                class: 'FooBar',
                extendsClass: ServiceWithoutInterface::class,
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
            ),
        ];

        yield [
            'foo.bar',
            ServiceOverridingParentMethod::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [ServiceInterface::class],
            ),
        ];

        yield [
            'foo.bar',
            FinalServiceExtendingParent::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [ServiceInterface::class],
            ),
        ];

        yield [
            'foo.bar',
            ServiceImplementingTwoInterfaces::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [
                    ServiceInterface::class,
                    OtherServiceInterface::class,
                ],
            ),
        ];

        yield [
            ServiceInterface::class,
            ServiceImplementingTwoInterfaces::class,
            ClassData::create(
                class: 'FooBar',
                useStatements: [
                    AsDecorator::class,
                    AutowireDecorated::class,
                ],
                implements: [
                    ServiceInterface::class,
                    OtherServiceInterface::class,
                ],
            ),
        ];
    }

    /** @dataProvider decorateAttributeDeclarationProvider */
    public function testGetDecorateAttributeDeclaration(string $serviceId, string $decoratedClassOrInterface, ?int $priority, ?int $onInvalid, string $expected, bool $idAsClassOrInterface)
    {
        $decoratorInfo = new DecoratorInfo('FooBar', $serviceId, $decoratedClassOrInterface, $priority, $onInvalid);

        $this->assertSame($expected, $decoratorInfo->getDecorateAttributeDeclaration());

        if ($idAsClassOrInterface) {
            $this->assertTrue($decoratorInfo->getClassData()->hasUseStatement($serviceId));
        }
    }

    public function decorateAttributeDeclarationProvider(): \Generator
    {
        yield ['foo.bar', ServiceInterface::class, null, null, '#[AsDecorator(decorates: \'foo.bar\')]', false];
        yield [ServiceInterface::class, ServiceInterface::class, null, null, '#[AsDecorator(decorates: ServiceInterface::class)]', true];
        yield ['foo.bar', ServiceInterface::class, 50, null, '#[AsDecorator(decorates: \'foo.bar\', priority: 50)]', false];
        yield ['foo.bar', ServiceInterface::class, null, 0, '#[AsDecorator(decorates: \'foo.bar\', onInvalid: ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)]', false];
        yield ['foo.bar', ServiceInterface::class, 50, 0, '#[AsDecorator(decorates: \'foo.bar\', priority: 50, onInvalid: ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)]', false];
    }

    public function testInvalidOnInvalid()
    {
        $this->expectException(RuntimeCommandException::class);
        new DecoratorInfo('FooBar', 'foo.bar', ServiceInterface::class, null, -1);
    }

    /** @dataProvider shortNameInnerTypeProvider */
    public function testGetShortNameInnerType(string $decoratedClassOrInterface, string $expected, array $inUseStatements)
    {
        $decoratorInfo = new DecoratorInfo('FooBar', 'foo.bar', $decoratedClassOrInterface);

        $this->assertSame($expected, $decoratorInfo->getShortNameInnerType());

        foreach ($inUseStatements as $inUseStatement) {
            $this->assertTrue($decoratorInfo->getClassData()->hasUseStatement($inUseStatement));
        }
    }

    public function shortNameInnerTypeProvider(): \Generator
    {
        yield [ServiceInterface::class, 'ServiceInterface', [ServiceInterface::class]];
        yield [ServiceImplementingInterface::class, 'ServiceInterface', [ServiceInterface::class]];
        yield [ServiceWithExtraMethods::class, 'ServiceWithExtraMethods', [ServiceWithExtraMethods::class]];
        yield [ServiceWithoutInterface::class, 'ServiceWithoutInterface', [ServiceWithoutInterface::class]];
        yield [ServiceOverridingParentMethod::class, 'ServiceInterface', [ServiceInterface::class]];
        yield [FinalServiceExtendingParent::class, 'ServiceInterface', [ServiceInterface::class]];
        yield [ServiceImplementingTwoInterfaces::class, 'ServiceInterface&OtherServiceInterface', [ServiceInterface::class, OtherServiceInterface::class]];
    }

    public function testGetPublicMethodsWithVariadicArgument()
    {
        $decoratorInfo = new DecoratorInfo('FooBar', 'foo.bar', ServiceWithVariadicMethod::class);

        $method = $decoratorInfo->getPublicMethods()['logMessages'];

        $this->assertSame(
            'public function logMessages(string $prefix, string ...$messages): void',
            $method->getDeclaration(),
        );
        $this->assertSame('$prefix, ...$messages', $method->getArgumentsUse());
    }

    /** @dataProvider aliasOnClassNameProvider */
    public function testAliasOnClassName(string $decoratorClassName, string $decoratedId, string $decoratedClassOrInterface, array $inUseStatements)
    {
        $decoratorInfo = new DecoratorInfo($decoratorClassName, $decoratedId, $decoratedClassOrInterface);

        foreach ($inUseStatements as $class => $alias) {
            $this->assertSame($alias, $decoratorInfo->getClassData()->getUseStatementShortName($class));
        }
    }

    public function aliasOnClassNameProvider(): \Generator
    {
        yield [
            'TheService\\ServiceImplementingInterface',
            SubServiceImplementingInterface::class,
            SubServiceImplementingInterface::class,
            [
                SubServiceImplementingInterface::class => 'ServiceServiceImplementingInterface',
            ],
        ];

        yield [
            'TheService\\ServiceWithExtraMethods',
            ServiceWithExtraMethods::class,
            ServiceWithExtraMethods::class,
            [
                ServiceWithExtraMethods::class => 'BaseServiceWithExtraMethods',
            ],
        ];

        yield [
            'TheService\\ServiceWithExtraMethods',
            SubServiceWithExtraMethods::class,
            SubServiceWithExtraMethods::class,
            [
                ServiceWithExtraMethods::class => 'BaseServiceWithExtraMethods',
            ],
        ];

        yield [
            'TheService\\ServiceOverridingParentMethod',
            SubServiceOverridingParentMethod::class,
            SubServiceOverridingParentMethod::class,
            [
                SubServiceOverridingParentMethod::class => 'BaseServiceOverridingParentMethod',
            ],
        ];
    }
}
