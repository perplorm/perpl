<?php

declare(strict_types = 1);

namespace Propel\Generator\Reverse;

use PDO;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use function count;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;

/**
 * SQLite database schema parser.
 */
class SqliteSchemaParser extends AbstractSchemaParser
{
    /**
     * @var bool
     */
    protected $addVendorInfo;

    /**
     * There really aren't any SQLite native types, so we're just
     * using the MySQL ones here.
     *
     * @return array<\Propel\Generator\Model\Datatype\ColumnType>
     */
    #[\Override]
    protected function buildTypeMapping(): array
    {
        return [
            'tinyint' => ColumnType::TINYINT,
            'smallint' => ColumnType::SMALLINT,
            'mediumint' => ColumnType::SMALLINT,
            'int' => ColumnType::INTEGER,
            'integer' => ColumnType::INTEGER,
            'bigint' => ColumnType::BIGINT,
            'int24' => ColumnType::BIGINT,
            'real' => ColumnType::REAL,
            'float' => ColumnType::FLOAT,
            'decimal' => ColumnType::DECIMAL,
            'numeric' => ColumnType::NUMERIC,
            'double' => ColumnType::DOUBLE,
            'char' => ColumnType::CHAR,
            'varchar' => ColumnType::VARCHAR,
            'date' => ColumnType::DATE,
            'time' => ColumnType::TIME,
            'year' => ColumnType::INTEGER,
            'datetime' => ColumnType::DATETIME,
            'timestamp' => ColumnType::TIMESTAMP,
            'tinyblob' => ColumnType::BINARY,
            'blob' => ColumnType::BLOB,
            'mediumblob' => ColumnType::VARBINARY,
            'longblob' => ColumnType::LONGVARBINARY,
            'longtext' => ColumnType::CLOB,
            'tinytext' => ColumnType::VARCHAR,
            'mediumtext' => ColumnType::LONGVARCHAR,
            'text' => ColumnType::LONGVARCHAR,
            'enum' => ColumnType::CHAR,
            'set' => ColumnType::CHAR,
            'uuid' => ColumnType::UUID,
        ];
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     * @param array<\Propel\Generator\Model\Table> $additionalTables
     *
     * @return int
     */
    #[\Override]
    public function parse(Database $database, array $additionalTables = []): int
    {
        if ($this->getGeneratorConfig()) {
            $this->addVendorInfo = (bool)$this->getGeneratorConfig()->getConfigProperty('migrations.addVendorInfo');
        }

        $this->parseTables($database);

        foreach ($additionalTables as $table) {
            $this->parseTables($database, $table);
        }

        // Now populate only columns.
        foreach ($database->getTables() as $table) {
            $this->addColumns($table);
        }

        // Now add indexes and constraints.
        foreach ($database->getTables() as $table) {
            $this->addIndexes($table);
            $this->addForeignKeys($table);
        }

        return count($database->getTables());
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     * @param \Propel\Generator\Model\Table|null $filterTable
     *
     * @return void
     */
    protected function parseTables(Database $database, ?Table $filterTable = null): void
    {
        $sql = "
        SELECT name
        FROM sqlite_master
        WHERE type='table'
        %filter%
        UNION ALL
        SELECT name
        FROM sqlite_temp_master
        WHERE type='table'
        %filter%
        ORDER BY name;";

        $filter = '';

        if ($filterTable) {
            $schema = $filterTable->getSchema();
            if ($schema) {
                $filter = sprintf(" AND name LIKE '%s§%%'", $schema);
            }
            $filter .= sprintf(" AND (name = '%s' OR name LIKE '%%§%1\$s')", $filterTable->getCommonName());
        } else {
            $schema = $database->getSchema();
            if ($schema) {
                $filter = sprintf(" AND name LIKE '%s§%%'", $schema);
            }
        }

        $sql = str_replace('%filter%', $filter, $sql);

        /** @var \Traversable $dataFetcher */
        $dataFetcher = $this->con->query($sql);

        // First load the tables (important that this happens before filling out details of tables)
        foreach ($dataFetcher as $row) {
            $tableName = $row[0];
            $tableSchema = '';

            if (substr($tableName, 0, 7) === 'sqlite_') {
                continue;
            }

            $pos = strpos($tableName, '§');
            if ($pos !== false) {
                $tableSchema = substr($tableName, 0, $pos);
                $tableName = substr($tableName, $pos + 2);
            }

            $table = new Table($tableName);

            if ($filterTable && $filterTable->getSchema()) {
                $table->setSchema($filterTable->getSchema());
            } else {
                if (!$database->getSchema() && $tableSchema) {
                    //we have no schema to filter, but this belongs to one, so next
                    continue;
                }
            }

            if ($tableName === $this->getMigrationTable()) {
                continue;
            }

            $table->setIdMethod($database->getDefaultIdMethod());
            $database->addTable($table);
        }
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
        $tableName = $table->getName();

        /** @var \PDOStatement $stmt */
        $stmt = $this->con->query("PRAGMA table_info('$tableName')");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['name'];

            $fulltype = $row['type'];
            $size = null;
            $scale = null;

            if (preg_match('/^([^\(]+)\(\s*(\d+)\s*,\s*(\d+)\s*\)$/', $fulltype, $matches)) {
                $type = $matches[1];
                $size = (int)$matches[2];
                $scale = (int)$matches[3];
            } elseif (preg_match('/^([^\(]+)\(\s*(\d+)\s*\)$/', $fulltype, $matches)) {
                $type = $matches[1];
                $size = (int)$matches[2];
            } else {
                $type = $fulltype;
            }
            $notNull = (bool)$row['notnull'];
            $default = $row['dflt_value'];

            $propelType = $this->getMappedPropelType(strtolower($type));

            if (!$propelType) {
                $propelType = Column::DEFAULT_TYPE;
                $this->warn('Column [' . $table->getName() . '.' . $name . '] has a column type (' . $type . ') that Propel does not support.');
            }

            $column = new Column($name);
            $column->setTable($table);
            $column->setUpTypeMapping($propelType);
            $column->getTypeMapping()->setSizeToValueIfNotNull($size);
            $column->getTypeMapping()->setScaleToValueIfNotNull($scale);

            if ($default !== null) {
                $isExpression = !str_starts_with($default, "'") && str_contains($default, '(');
                if (!$isExpression) {
                    $default = str_replace("'", '', $default);
                } elseif ($default === 'datetime(CURRENT_TIMESTAMP, \'localtime\')') {
                    $default = 'CURRENT_TIMESTAMP';
                }
                $column->getTypeMapping()->createDefaultValue($default, $isExpression);
            }

            $column->setNotNull($notNull);

            if (0 < $row['pk'] + 0) {
                $column->setPrimaryKey(true);
            }

            if ($column->isPrimaryKey()) {
                // check if autoIncrement
                /** @var \PDOStatement $autoIncrementStmt */
                $autoIncrementStmt = $this->con->prepare('
                SELECT tbl_name
                FROM sqlite_master
                WHERE
                  tbl_name = ?
                AND
                  sql LIKE "%AUTOINCREMENT%"
                ');
                $autoIncrementStmt->execute([$table->getName()]);
                $autoincrementRow = $autoIncrementStmt->fetch(PDO::FETCH_ASSOC);
                if ($autoincrementRow && $autoincrementRow['tbl_name'] == $table->getName()) {
                    $column->setAutoIncrement(true);
                }
            }

            $table->addColumn($column);
        }
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    protected function addForeignKeys(Table $table): void
    {
        $database = $table->getDatabase();

        /** @var \PDOStatement $stmt */
        $stmt = $this->con->query('PRAGMA foreign_key_list("' . $table->getName() . '")');

        $lastId = null;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($lastId !== $row['id']) {
                $fk = new ForeignKey();

                $onDelete = $row['on_delete'];
                if ($onDelete && $onDelete !== 'NO ACTION') {
                    $fk->setOnDelete($onDelete);
                }

                $onUpdate = $row['on_update'];
                if ($onUpdate && $onUpdate !== 'NO ACTION') {
                    $fk->setOnUpdate($onUpdate);
                }

                $foreignTable = $database->getTable($row['table'], true);

                if (!$foreignTable) {
                    continue;
                }

                // we need the reference earlier to build the FK name in Table class to prevent adding FK twice
                $fk->addReference($row['from'], $row['to']);
                $fk->setForeignTableCommonName($foreignTable->getCommonName());
                $table->addForeignKey($fk);

                $fk->setForeignTableCommonName($foreignTable->getCommonName());
                if ($table->guessSchemaName() != $foreignTable->guessSchemaName()) {
                    $fk->setForeignSchemaName($foreignTable->guessSchemaName());
                }
                $lastId = $row['id'];
            } else {
                $fk->addReference($row['from'], $row['to']);
            }
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
        /** @var \PDOStatement $stmt */
        $stmt = $this->con->query('PRAGMA index_list("' . $table->getName() . '")');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['name'];
            $internalName = $name;

            if (strpos($name, 'sqlite_autoindex') === 0) {
                $internalName = '';
            }

            $index = $row['unique'] ? new Unique($internalName) : new Index($internalName);

            /** @var \PDOStatement $stmt2 */
            $stmt2 = $this->con->query("PRAGMA index_info('" . $name . "')");
            while ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $colname = $row2['name'];
                $index->addColumn($table->getColumn($colname));
            }

            if (count($table->getPrimaryKey()) === 1 && count($index->getColumns()) === 1) {
                // exclude the primary unique index, since it's autogenerated by sqlite
                if ($table->getPrimaryKey()[0]->getName() === $index->getColumns()[0]) {
                    continue;
                }
            }

            if ($index instanceof Unique) {
                $table->addUnique($index);
            } else {
                $table->addIndex($index);
            }
        }
    }
}
