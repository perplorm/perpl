<?php

declare(strict_types = 1);

namespace Propel\Tests\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use Exception;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Tests\TestCaseFixtures;
use function restore_error_handler;
use function set_error_handler;
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
        set_error_handler(static function (int $errno, string $errstr) {
            throw new Exception($errstr, $errno);
        }, E_USER_NOTICE);

        $c = new Criteria();

        $this->expectExceptionMessage("Could not resolve column 'title', assuming PDO type is string. Consider setting PDO type yourself.");
        try {
            $c->setUpdateValue('title', 'Updated Title');
        } finally {
            restore_error_handler();
        }
    }
}
