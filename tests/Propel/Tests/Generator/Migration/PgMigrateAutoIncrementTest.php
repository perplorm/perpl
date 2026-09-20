<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Util\QuickBuilder;

/**
 * @group database
 * @group pgsql
 */
class PgMigrateAutoIncrementTest extends MigrationTestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $serverVersion = $this->con->query('SHOW server_version;')->fetchColumn();
        if (version_compare($serverVersion, '14', '<')) {
            $this->markTestSkipped("Postgres supports generated identity columns from version 14, but current version is $serverVersion");
        }
        $this->con->exec('DROP TABLE IF EXISTS migration.migrate_auto_increment;');

        
    }

    public function testSwitchingMethodPreservesId(): void
    {
        $this->buildIdTable(null, 'PgNoMethod');
        \PostgresAiTest\Map\PgNoMethodTableMap::doDeleteAll();
        $n = new \PostgresAiTest\PgNoMethod();
        $n->setId(500)->setPayload('no id')->save();

        $this->buildIdTable(IdMethod::SEQUENCE, 'PgSequenced');
        $this->assertStoreAndReloadWithId(new \PostgresAiTest\PgSequenced(), 501);

        $this->buildIdTable(IdMethod::IDENTITY, 'PgIdentified');
        $this->assertStoreAndReloadWithId(new \PostgresAiTest\PgIdentified(), 502);
    }

    protected function assertStoreAndReloadWithId($entity, int $expectedId)
    {
        $payload = $entity::class . '_' . time();
        $entity->setPayload($payload)->save();
        $this->assertSame($expectedId, $entity->getId());
        $entity->reload();
        $this->assertSame($payload, $entity->getPayload());
    }

    /**
     * Set up DB table and optionally build table classes (if $phpTableName is given)
     *
     * @param IdMethod|null $idMethod
     * @param string|null $phpTableName
     * @return void
     */
    protected function buildIdTable(IdMethod|null $idMethod, string|null $phpTableName = null)
    {
        $databaseXml = $this->buildDatabaseTableXml($idMethod, $phpTableName);
        $this->applyXmlAndTest($databaseXml, true);

        if ($phpTableName) {
            $builder = new QuickBuilder();
            $builder->setPlatform(new PgsqlPlatform());
            $builder->setSchemaXml($databaseXml);
            $builder->buildClasses();
        }
    }

    protected function buildDatabaseTableXml(IdMethod|null $idMethod, string|null $phpTableName = null): string
    {
        $methodAttribute = ($idMethod) ? "idMethod=\"$idMethod->value\"" : '';
        $isAutoIncrement = ($idMethod) ? 'true' : "false";
        $phpName = $phpTableName ? "phpName=\"$phpTableName\"" : '';

        return <<< EOF
<database name="migration" schema="migration" namespace="PostgresAiTest">
    <table name="migrate_auto_increment" $methodAttribute $phpName>
        <column name="id" type="integer" primaryKey="true" autoIncrement="$isAutoIncrement"/>
        <column name="payload"/>
    </table>
</database>
EOF;
    }

    protected function applyWithFail(string $description, ?IdMethod $idMethod, bool $changeRequired, string|null $phpTableName = null)
    {
        $databaseXml = $this->buildDatabaseTableXml($idMethod, $phpTableName);

        try {
            $this->applyXmlAndTest($databaseXml, $changeRequired);
        } catch (BuildException $e) {
            $this->fail("$description\n\n" . $e->getMessage());
        }
    }

    #[DataProvider('migrateAutoIncrementDataProvider')]
    public function testMigrateAutoIncrement(string $description, ?IdMethod $fromIdMethod, ?IdMethod $toIdMethod, bool $expectChange = true): void
    {
        $this->applyWithFail("$description - failed to apply initial schema", $fromIdMethod, false);
        $this->applyWithFail("$description - failed to apply migration", $toIdMethod, $expectChange);
    }

    public static function migrateAutoIncrementDataProvider(): array
    {
        return [
            // description, first id method, second id method, expect change (optional)
            ['To sequence type', null, IdMethod::SEQUENCE],
            ['To identity type', null, IdMethod::IDENTITY],
            ['Sequence is native type', IdMethod::NATIVE, IdMethod::SEQUENCE, false],
            ['From sequence to identity', IdMethod::SEQUENCE, IdMethod::IDENTITY],
            ['From identity to sequence', IdMethod::IDENTITY, IdMethod::SEQUENCE],
            ['Remove sequence type', IdMethod::SEQUENCE, null],
            ['Remove identity type', IdMethod::IDENTITY, null],
        ];
    }
}
