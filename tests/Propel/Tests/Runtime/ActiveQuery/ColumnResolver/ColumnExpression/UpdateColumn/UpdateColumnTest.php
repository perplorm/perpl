<?php

declare(strict_types = 1);

namespace Propel\Tests\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Tests\TestCaseFixtures;
use const E_USER_NOTICE;

/**
 * @group database
 */
class UpdateColumnTest extends TestCaseFixtures
{
    /**
     * @return void
     */
    public function testMissingPdoTypeCreatesNotice(): void
    {
        $c = new Criteria();
        $message = "Could not resolve column 'title', assuming PDO type is string. Consider setting PDO type yourself.";
        $this->expectErrorLevel(E_USER_NOTICE, fn() => $c->setUpdateValue('title', 'Updated Title'), $message);
    }
}
