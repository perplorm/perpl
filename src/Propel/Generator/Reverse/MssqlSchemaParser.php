<?php

declare(strict_types = 1);

namespace Propel\Generator\Reverse;

// TODO: to remove
use PDO;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use RuntimeException;
use function count;
use function preg_replace;
use function sprintf;
use function strtolower;

/**
 * Microsoft SQL Server database schema parser.
 */
class MssqlSchemaParser extends AbstractSchemaParser
{
    /**
     * @see AbstractSchemaParser::buildTypeMapping()
     *
     * @return array<\Propel\Generator\Model\Datatype\ColumnType>
     */
    #[\Override]
    protected function buildTypeMapping(): array
    {
        return [
            'binary' => ColumnType::BINARY,
            'bit' => ColumnType::BOOLEAN,
            'char' => ColumnType::CHAR,
            'datetime' => ColumnType::TIMESTAMP,
            'decimal() identity' => ColumnType::DECIMAL,
            'decimal' => ColumnType::DECIMAL,
            'image' => ColumnType::LONGVARBINARY,
            'int' => ColumnType::INTEGER,
            'int identity' => ColumnType::INTEGER,
            'integer' => ColumnType::INTEGER,
            'money' => ColumnType::DECIMAL,
            'nchar' => ColumnType::CHAR,
            'ntext' => ColumnType::LONGVARCHAR,
            'numeric() identity' => ColumnType::NUMERIC,
            'numeric' => ColumnType::NUMERIC,
            'nvarchar' => ColumnType::VARCHAR,
            'real' => ColumnType::REAL,
            'float' => ColumnType::FLOAT,
            'smalldatetime' => ColumnType::TIMESTAMP,
            'smallint' => ColumnType::SMALLINT,
            'smallint identity' => ColumnType::SMALLINT,
            'smallmoney' => ColumnType::DECIMAL,
            'sysname' => ColumnType::VARCHAR,
            'text' => ColumnType::LONGVARCHAR,
            'timestamp' => ColumnType::BINARY,
            'tinyint identity' => ColumnType::TINYINT,
            'tinyint' => ColumnType::TINYINT,
            'uniqueidentifier' => ColumnType::UUID,
            'varbinary' => ColumnType::VARBINARY,
            'varbinary(max)' => ColumnType::CLOB,
            'varchar' => ColumnType::VARCHAR,
            'varchar(max)' => ColumnType::CLOB,
            'geometry' => ColumnType::GEOMETRY,
            // SQL Server 2000 only
            'bigint identity' => ColumnType::BIGINT,
            'bigint' => ColumnType::BIGINT,
            'sql_variant' => ColumnType::VARCHAR,
        ];
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     * @param array $additionalTables
     *
     * @throws \RuntimeException
     *
     * @return int
     */
    #[\Override]
    public function parse(Database $database, array $additionalTables = []): int
    {
        $dataFetcher = $this->con->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'dtproperties'");

        if ($dataFetcher === false) {
            throw new RuntimeException('PdoConnection::query() did not return a result set as a statement object.');
        }

        // First load the tables (important that this happens before filling out details of tables)
        $tables = [];
        foreach ($dataFetcher as $row) {
            $name = $this->cleanDelimitedIdentifiers($row[0]);
            if ($name === $this->getMigrationTable()) {
                continue;
            }
            $table = new Table($name);
            $table->setIdMethod($database->getDefaultIdMethod());
            $database->addTable($table);
            $tables[] = $table;
        }

        // Now populate only columns.
        foreach ($tables as $table) {
            $this->addColumns($table);
        }

        // Now add indexes and constraints.
        foreach ($tables as $table) {
            $this->addForeignKeys($table);
            $this->addIndexes($table);
            $this->addPrimaryKey($table);
        }

        return count($tables);
    }

    /**
     * Adds Columns to the specified table.
     *
     * @param \Propel\Generator\Model\Table $table The Table model class to add columns to.
     *
     * @return void
     */
    protected function addColumns(Table $table): void
    {
        /** @var \Propel\Runtime\DataFetcher\PDODataFetcher $dataFetcher */
        $dataFetcher = $this->con->query("sp_columns '" . $table->getName() . "'");
        $dataFetcher->setStyle(PDO::FETCH_ASSOC);

        foreach ($dataFetcher as $row) {
            $name = $this->cleanDelimitedIdentifiers($row['COLUMN_NAME']);
            $type = $row['TYPE_NAME'];
            $size = $row['LENGTH'];
            $isNullable = $row['NULLABLE'];
            $default = $row['COLUMN_DEF'];
            $scale = $row['SCALE'];
            $autoincrement = false;
            if (strtolower($type) === 'int identity') {
                $autoincrement = true;
            }

            $propelType = $this->getMappedPropelType($type);
            if (!$propelType) {
                $propelType = Column::DEFAULT_TYPE;
                $this->warn(sprintf('Column [%s.%s] has a column type (%s) that Propel does not support.', $table->getName(), $name, $type));
            }

            $column = new Column($name);
            $column->setTable($table);
            $column->setUpTypeMapping($propelType);
            $column->getTypeMapping()->setSizeToValueIfNotNull($size);
            $column->getTypeMapping()->setScaleToValueIfNotNull($scale);
            if ($default !== null) {
                $column->getTypeMapping()->createDefaultValue($default);
            }
            $column->setAutoIncrement($autoincrement);
            $column->setNotNull(!$isNullable);

            $table->addColumn($column);
        }
    }

    /**
     * Load foreign keys for this table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    protected function addForeignKeys(Table $table): void
    {
        $database = $table->getDatabase();

        /** @var \Propel\Runtime\DataFetcher\PDODataFetcher $dataFetcher */
        $dataFetcher = $this->con->query("select fk.name as CONSTRAINT_NAME, lcol.name as COLUMN_NAME, rtab.name as FK_TABLE_NAME, rcol.name as FK_COLUMN_NAME
         from sys.foreign_keys as fk
         inner join sys.foreign_key_columns ref on ref.constraint_object_id = fk.object_id
         inner join sys.columns lcol on lcol.object_id = ref.parent_object_id and lcol.column_id = ref.parent_column_id
         inner join sys.columns rcol on rcol.object_id = ref.referenced_object_id and rcol.column_id = ref.referenced_column_id
         inner join sys.tables rtab on rtab.object_id = ref.referenced_object_id
         where fk.parent_object_id = OBJECT_ID('" . $table->getName() . "')");
        $dataFetcher->setStyle(PDO::FETCH_ASSOC);

        $foreignKeys = []; // local store to avoid duplicates
        foreach ($dataFetcher as $row) {
            $name = $this->cleanDelimitedIdentifiers($row['CONSTRAINT_NAME']);
            $lcol = $this->cleanDelimitedIdentifiers($row['COLUMN_NAME']);
            $ftbl = $this->cleanDelimitedIdentifiers($row['FK_TABLE_NAME']);
            $fcol = $this->cleanDelimitedIdentifiers($row['FK_COLUMN_NAME']);

            $foreignTable = $database->getTable($ftbl);
            $foreignColumn = $foreignTable->getColumn($fcol);
            $localColumn = $table->getColumn($lcol);

            if (!isset($foreignKeys[$name])) {
                $fk = new ForeignKey($name);
                $fk->setForeignTableCommonName($foreignTable->getCommonName());
                $fk->setForeignSchemaName($foreignTable->getSchema());
                //$fk->setOnDelete($fkactions['ON DELETE']);
                //$fk->setOnUpdate($fkactions['ON UPDATE']);
                $table->addForeignKey($fk);
                $foreignKeys[$name] = $fk;
            }
            $foreignKeys[$name]->addReference($localColumn, $foreignColumn);
        }
    }

    /**
     * Load indexes for this table
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    protected function addIndexes(Table $table): void
    {
        /** @var \Propel\Runtime\DataFetcher\PDODataFetcher $dataFetcher */
        $dataFetcher = $this->con->query("sp_indexes_rowset '" . $table->getName() . "'");
        $dataFetcher->setStyle(PDO::FETCH_ASSOC);

        $indexes = [];
        foreach ($dataFetcher as $row) {
            $colName = $this->cleanDelimitedIdentifiers($row['COLUMN_NAME']);
            $name = $this->cleanDelimitedIdentifiers($row['INDEX_NAME']);

            $isPk = $this->cleanDelimitedIdentifiers($row['PRIMARY_KEY']);
            $isUnique = $this->cleanDelimitedIdentifiers($row['UNIQUE']);

            $localColumn = $table->getColumn($colName);

            // ignore PRIMARY index
            if ($isPk) {
                continue;
            }

            if (!isset($indexes[$name])) {
                if ($isUnique) {
                    $indexes[$name] = new Unique($name);
                } else {
                    $indexes[$name] = new Index($name);
                }
                $indexes[$name]->setTable($table);
            }

            $indexes[$name]->addColumn($localColumn);
        }

        foreach ($indexes as $index) {
            if ($index instanceof Unique) {
                $table->addUnique($index);
            } else {
                $table->addIndex($index);
            }
        }
    }

    /**
     * Loads the primary key for this table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function addPrimaryKey(Table $table): void
    {
        $dataFetcher = $this->con->query("SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            INNER JOIN INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE ON
            INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_NAME = INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE.constraint_name
            WHERE     (INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'PRIMARY KEY') AND
            (INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = '" . $table->getName() . "')");

        if ($dataFetcher === false) {
            throw new RuntimeException('PdoConnection::query() did not return a result set as a statement object.');
        }

        // Loop through the returned results, grouping the same key_name together
        // adding each column for that key.
        foreach ($dataFetcher as $row) {
            $name = $this->cleanDelimitedIdentifiers($row[0]);
            $table->getColumn($name)->setPrimaryKey(true);
        }
    }

    /**
     * according to the identifier definition, we have to clean simple quote (') around the identifier name
     * returns by mssql
     *
     * @see http://msdn.microsoft.com/library/ms175874.aspx
     *
     * @param string $identifier
     *
     * @return string
     */
    protected function cleanDelimitedIdentifiers(string $identifier): string
    {
        return preg_replace('/^\'(.*)\'$/U', '$1', $identifier);
    }
}
