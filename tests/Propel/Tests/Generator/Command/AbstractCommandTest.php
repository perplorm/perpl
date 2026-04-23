<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\AbstractCommand;
use Propel\Tests\TestCase;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class AbstractCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function testParseConnectionWithCredentials(): void
    {
        $user = 'root';
        $password = 'H7{“Qj1n>\%28=;P';
        $connectionName = 'bookstore';
        $dsn = 'mysql:host=127.0.0.1;dbname=test';
        $connection = "$connectionName=$dsn;user=$user;password=" . urlencode($password);

        $command = new class extends AbstractCommand{};
        $result = $this->callMethod($command, 'parseConnection', [$connection]);

        $this->assertCount(3, $result);
        $this->assertEquals($connectionName, $result[0]);
        $this->assertEquals($dsn, $result[1], 'DSN should not contain user and password parameters');
        $this->assertArrayHasKey('adapter', $result[2]);
        $this->assertEquals('mysql', $result[2]['adapter']);
        $this->assertArrayHasKey('user', $result[2]);
        $this->assertEquals($user, $result[2]['user']);
        $this->assertArrayHasKey('password', $result[2]);
        $this->assertEquals($password, $result[2]['password']);
    }

    /**
     * @return void
     */
    public function testParseConnectionWithoutCredentials(): void
    {
        $connectionName = 'bookstore';
        $dsn = 'sqlite:/tmp/test.sq3';
        $connection = "$connectionName=$dsn";

        $command = new class extends AbstractCommand{};
        $result = $this->callMethod($command, 'parseConnection', [$connection]);

        $this->assertCount(3, $result);
        $this->assertEquals($connectionName, $result[0]);
        $this->assertEquals($dsn, $result[1], 'DSN should not contain user and password parameters');
        $this->assertArrayHasKey('adapter', $result[2]);
        $this->assertEquals('sqlite', $result[2]['adapter']);
        $this->assertArrayNotHasKey('user', $result[2]);
        $this->assertArrayNotHasKey('password', $result[2]);
    }

    /**
     * @return void
     */
    public function testRecursiveSearch(): void
    {
        $command = new class extends AbstractCommand{};
        $schemaDir = realpath(__DIR__ . '/util/recursive-schema');

        $filesInRecursiveSearch = $this->callMethod($command, 'findSchemasInDirectory', [$schemaDir, true]);
        $this->assertCount(3, $filesInRecursiveSearch);

        $filesInFlatSearch = $this->callMethod($command, 'findSchemasInDirectory', [$schemaDir, false]);
        $this->assertCount(1, $filesInFlatSearch);
    }
}
