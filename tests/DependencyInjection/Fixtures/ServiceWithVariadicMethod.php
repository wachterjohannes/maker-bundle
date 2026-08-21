<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures;

// Method with a variadic argument, to check the generated signature and forwarding call.
class ServiceWithVariadicMethod
{
    public function logMessages(string $prefix, string ...$messages): void
    {
    }
}
