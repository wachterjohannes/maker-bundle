<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\Sub;

use Symfony\Bundle\MakerBundle\Tests\DependencyInjection\Fixtures\ServiceOverridingParentMethod as BaseService;

// Adds a method to an aliased parent of the same short name, to test alias generation.
class ServiceOverridingParentMethod extends BaseService
{
    public function blabla(): void
    {
    }
}
