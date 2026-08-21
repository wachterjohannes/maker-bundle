<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\Maker;

use Symfony\Bundle\MakerBundle\Maker\MakeDecorator;
use Symfony\Bundle\MakerBundle\Test\MakerTestCase;
use Symfony\Bundle\MakerBundle\Test\MakerTestDetails;
use Symfony\Bundle\MakerBundle\Test\MakerTestRunner;

class MakeDecoratorTest extends MakerTestCase
{
    protected function getMakerClass(): string
    {
        return MakeDecorator::class;
    }

    private static function buildDecoratorTest(): MakerTestDetails
    {
        return self::buildMakerTest()
            ->preRun(static function (MakerTestRunner $runner) {
                $runner->copy(
                    'make-decorator/basic_setup',
                    ''
                );

                $runner->modifyYamlFile('config/services.yaml', function (array $config) {
                    $config['services']['App\\Service\\'] = [
                        'resource' => '../src/Service',
                        'public' => true,
                    ];

                    return $config;
                });
            });
    }

    public static function getTestDetails(): \Generator
    {
        yield 'it_generates_basic_implements' => [self::buildDecoratorTest()
            ->run(static function (MakerTestRunner $runner) {
                $runner->runMaker([
                    'App\\Service\\FooService',
                    'GeneratedServiceDecorator',
                ]);

                self::runFormTest($runner, 'it_generates_basic_implements.php');
            }),
        ];

        yield 'it_generates_multiple_implements' => [self::buildDecoratorTest()
            ->run(static function (MakerTestRunner $runner) {
                $runner->runMaker([
                    'App\\Service\\MultipleImpService',
                    'GeneratedServiceDecorator',
                ]);

                self::runFormTest($runner, 'it_generates_multiple_implements.php');
            }),
        ];

        yield 'it_generates_force_extends' => [self::buildDecoratorTest()
            ->run(static function (MakerTestRunner $runner) {
                $runner->runMaker([
                    'App\\Service\\ForExtendService',
                    'GeneratedServiceDecorator',
                ]);

                self::runFormTest($runner, 'it_generates_force_extends.php');
            }),
        ];
    }

    private static function runFormTest(MakerTestRunner $runner, string $filename): void
    {
        $runner->copy(
            'make-decorator/tests/'.$filename,
            'tests/GeneratedDecoratorTest.php'
        );

        $runner->runTests();
    }
}
