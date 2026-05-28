<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime;

use Propel\Runtime\Perpl;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Propel\Tests\Helpers\BaseTestCase;
use Propel\Runtime\Exception\PropelException;

class PerplTest extends BaseTestCase
{
    protected static $initialServiceContainer;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$initialServiceContainer = Perpl::getServiceContainer();
    }
    
    public function tearDown(): void
    {
        Perpl::setServiceContainer(static::$initialServiceContainer);
    }
    
    /**
     * @return void
     */
    public function testGetServiceContainerReturnsAServiceContainer()
    {
        $this->assertInstanceOf(ServiceContainerInterface::class, Perpl::getServiceContainer());
    }

    /**
     * @return void
     */
    public function testGetServiceContainerAlwaysReturnsTheSameInstance()
    {
        $sc1 = Perpl::getServiceContainer();
        $sc2 = Perpl::getServiceContainer();
        $this->assertSame($sc1, $sc2);
    }

    /**
     * @return void
     */
    public function testSetServiceContainerOverridesTheExistingServiceContainer()
    {
        $newSC = new StandardServiceContainer();
        Perpl::setServiceContainer($newSC);
        $this->assertSame($newSC, Perpl::getServiceContainer());
    }
    
    public function testGetStandardServiceContainerWithDefaultContainer()
    {
        $sc = Perpl::getStandardServiceContainer();
        $this->assertInstanceOf(StandardServiceContainer::class, $sc);
    }
    
    
    public function testGetStandardServiceContainerThrowsErrorWithNonStandardContainer()
    {
        $sc = $this->createMock(ServiceContainerInterface::class);
        Perpl::setServiceContainer($sc);
        $this->expectException(PropelException::class);
        Perpl::getStandardServiceContainer();
    }
    
}
