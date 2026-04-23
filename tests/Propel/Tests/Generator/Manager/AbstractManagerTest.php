<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Manager;

use DOMDocument;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Manager\AbstractManager;
use Propel\Tests\TestCase;


class AbstractManagerTest extends TestCase
{
    private $manager;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->manager = new Manager();
    }

    /**
     * @return void
     */
    public function testIncludeExternalSchemaWithRelativePathTo()
    {
        // include book.schema.xml, which includes external author schema
        $schemaXml = <<<EOT
<?xml version="1.0" encoding="ISO-8859-1" standalone="no"?>
<database package="core.book" name="bookstore" defaultIdMethod="native" namespace="Propel\Tests\Bookstore">
  <external-schema filename="../../Resources/external-schemas/book.schema.xml" referenceOnly="true"/>
</database>

EOT;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($schemaXml);
        $numberOfIncludedSchemas = $this->manager->callIncludeExternalSchemas($dom, __DIR__);
        $this->assertEquals(1, $numberOfIncludedSchemas);

        $definedTables = $dom->getElementsByTagName('table');
        $this->assertEquals(2, $definedTables->length);
        $expectedTablesInOrder = ['book', 'author'];
        foreach ($expectedTablesInOrder as $index => $expectedTableName) {
            $table = $definedTables->item($index);
            $tableName = $table->getAttribute('name');
            $this->assertEquals($expectedTableName, $tableName);
            $skipSql = $table->getAttribute('skipSql');
            $this->assertEquals('true', $skipSql);
        }
    }

    /**
     * @return void
     */
    public function testIncludeExternalSchemaThrowsExceptionForMissingFiles()
    {
        // include book.schema.xml, which includes external author schema
        $schemaXml = <<<EOT
<?xml version="1.0" encoding="ISO-8859-1" standalone="no"?>
<database package="core.book" name="bookstore" defaultIdMethod="native" namespace="Propel\Tests\Bookstore">
  <external-schema filename="../ThisFileNameDoesNotExistBababuiBababui/book.schema.xml" referenceOnly="true"/>
</database>

EOT;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($schemaXml);
        $this->expectException(BuildException::class);
        $this->manager->callIncludeExternalSchemas($dom, __DIR__);
    }

    public function testSchemaFileParseException(): void
    {
        // newlines before xml declaration is prohibited
        $invalidSchemaXml = <<<EOT

<?xml version="1.0" encoding="ISO-8859-1" standalone="no"?>
EOT;
        $schemaFile = tmpfile();
        try {
            fwrite($schemaFile, $invalidSchemaXml);
            $schemaFilePathName = stream_get_meta_data($schemaFile)['uri'];

            $this->expectException(SchemaException::class);
            $this->expectExceptionMessage('Error parsing schema file: DOMDocument::load(): XML declaration allowed only at the start of the document in ');
            $this->callMethod($this->manager, 'loadSchemaDomDocument', [$schemaFilePathName]);
        } finally {
            fclose($schemaFile); // this removes the file
        }
    }
}

class Manager extends AbstractManager
{
    public function callIncludeExternalSchemas(DOMDocument $dom, $srcDir)
    {
        return $this->includeExternalSchemas($dom, $srcDir);
    }
}
