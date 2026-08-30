<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\DependencyInjection\DecoratorHelper;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\FinalServiceExtendingParent;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceImplementingInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceImplementingTwoInterfaces;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceInterface;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceOverridingParentMethod;
use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\Sub\ServiceImplementingInterface as SubServiceImplementingInterface;

class DecoratorHelperTest extends TestCase
{
    public function testSuggestIds()
    {
        $this->assertSame([
            'ServiceImplementingInterface',
            'ServiceOverridingParentMethod',
            'FinalServiceExtendingParent',
            'ServiceImplementingTwoInterfaces',
            'ServiceInterface',
            'bar.service_d',
            'foo.service_e',
            ServiceInterface::class,
            ServiceImplementingTwoInterfaces::class,
            ServiceImplementingInterface::class,
            SubServiceImplementingInterface::class,
        ], $this->getHelper()->suggestIds());
    }

    /** @dataProvider realIdsProvider */
    public function testGetRealIds(string $id, ?string $expected)
    {
        $this->assertSame($expected, $this->getHelper()->getRealId($id));
    }

    public function realIdsProvider(): \Generator
    {
        yield ['bar.service_d', 'bar.service_d'];
        yield ['foo.service_e', 'foo.service_e'];
        yield [ServiceInterface::class, ServiceInterface::class];
        yield [ServiceImplementingTwoInterfaces::class, ServiceImplementingTwoInterfaces::class];
        yield [ServiceImplementingInterface::class, ServiceImplementingInterface::class];
        yield [SubServiceImplementingInterface::class, SubServiceImplementingInterface::class];
        yield ['ServiceImplementingInterface', null];
        yield ['ServiceOverridingParentMethod', 'bar.service_d'];
        yield ['FinalServiceExtendingParent', 'foo.service_e'];
        yield ['ServiceImplementingTwoInterfaces', ServiceImplementingTwoInterfaces::class];
        yield ['ServiceInterface', ServiceInterface::class];
        yield ['ServiceeeInterface', null];
        yield ['NotExisting', null];
    }

    /** @dataProvider guessRealIdsProvider */
    public function testGuessRealIds(string $id, array $expected)
    {
        $this->assertSame($expected, $this->getHelper()->guessRealIds($id));
    }

    public function guessRealIdsProvider(): \Generator
    {
        yield [
            'ServiceImplementingInterface',
            [
                ServiceImplementingInterface::class,
                SubServiceImplementingInterface::class,
            ],
        ];

        yield [
            'ServiceImplementingInterfacee',
            [
                ServiceImplementingInterface::class,
                SubServiceImplementingInterface::class,
            ],
        ];

        yield ['ServiceeeInterface', [ServiceInterface::class]];
        yield ['baar.servicce_d', ['bar.service_d']];
        yield ['baaaaaar.servicce_d', []];
        yield ['NotExisting', []];
    }

    /** @dataProvider classProvider */
    public function testGetClass(string $id, string $expected)
    {
        $this->assertSame($expected, $this->getHelper()->getClass($id));
    }

    public function classProvider(): \Generator
    {
        yield ['bar.service_d', ServiceOverridingParentMethod::class];
        yield ['foo.service_e', FinalServiceExtendingParent::class];
        yield [ServiceImplementingTwoInterfaces::class, ServiceImplementingTwoInterfaces::class];
        yield [ServiceImplementingInterface::class, ServiceImplementingInterface::class];
        yield [SubServiceImplementingInterface::class, SubServiceImplementingInterface::class];
    }

    public function testInvalidGetClass()
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('Cannot getClass for id "NotExisting".');
        $this->getHelper()->getClass('NotExisting');
    }

    private function getHelper(): DecoratorHelper
    {
        return new DecoratorHelper(
            [
                'bar.service_d',
                'foo.service_e',
                ServiceInterface::class,
                ServiceImplementingTwoInterfaces::class,
                ServiceImplementingInterface::class,
                SubServiceImplementingInterface::class,
            ], [
                'bar.service_d' => ServiceOverridingParentMethod::class,
                'foo.service_e' => FinalServiceExtendingParent::class,
                ServiceInterface::class => ServiceImplementingInterface::class,
            ], [
                'ServiceImplementingInterface' => [
                    ServiceImplementingInterface::class,
                    SubServiceImplementingInterface::class,
                ],
                'ServiceOverridingParentMethod' => [
                    'bar.service_d',
                ],
                'FinalServiceExtendingParent' => [
                    'foo.service_e',
                ],
                'ServiceImplementingTwoInterfaces' => [
                    ServiceImplementingTwoInterfaces::class,
                ],
                'ServiceInterface' => [
                    ServiceInterface::class,
                ],
            ],
        );
    }
}
