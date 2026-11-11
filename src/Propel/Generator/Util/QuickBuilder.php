<?php

declare(strict_types = 1);

namespace Propel\Generator\Util;

use Exception;
use PDO;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Platform\SqlitePlatform;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Runtime\Adapter\AdapterInterface;
use Propel\Runtime\Adapter\Pdo\SqliteAdapter;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Perpl;
use RuntimeException;
use function array_filter;
use function array_map;
use function array_slice;
use function assert;
use function count;
use function explode;
use function file_put_contents;
use function function_exists;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function mkdir;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function sys_get_temp_dir;
use function token_get_all;
use const PHP_EOL;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_WHITESPACE;

class QuickBuilder
{
    use VfsTrait;

    /**
     * Used to globally disable building to Vfs
     *
     * @var bool
     */
    public static bool $disableVfs = false;

    protected string $schemaXml = '';

    protected string $schemaName = '';

    protected PlatformInterface|null $platform = null;

    protected AbstractGeneratorConfig|null $config = null;

    protected Database|null $database = null;

    protected SchemaParserInterface|null $parser = null;

    /**
     * Identifier quoting for reversed database.
     */
    protected bool $identifierQuoting = false;

    /**
     * If use the virtual or physical filesystem.
     */
    protected bool $vfs = true;

    /**
     * @param string $schemaXml
     *
     * @return void
     */
    public function setSchemaXml(string $schemaXml): void
    {
        $this->schemaXml = $schemaXml;
    }

    /**
     * @return string
     */
    public function getSchemaXml(): string
    {
        return $this->schemaXml;
    }

    /**
     * @param string $schemaName
     *
     * @return void
     */
    public function setSchemaName(string $schemaName): void
    {
        $this->schemaName = $schemaName;
    }

    /**
     * @return string
     */
    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    /**
     * @param \Propel\Generator\Reverse\SchemaParserInterface $parser
     *
     * @return void
     */
    public function setParser(SchemaParserInterface $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * @return \Propel\Generator\Reverse\SchemaParserInterface|null
     */
    public function getParser(): ?SchemaParserInterface
    {
        return $this->parser;
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return void
     */
    public function setPlatform(PlatformInterface $platform): void
    {
        $this->platform = $platform;
    }

    /**
     * @return \Propel\Generator\Platform\PlatformInterface
     */
    public function getPlatform(): PlatformInterface
    {
        if ($this->platform === null) {
            $this->platform = new SqlitePlatform();
        }

        $this->platform->setIdentifierQuoting($this->identifierQuoting);

        return $this->platform;
    }

    /**
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $config
     *
     * @return void
     */
    public function setConfig(AbstractGeneratorConfig $config): void
    {
        $this->config = $config;
    }

    /**
     * @return \Propel\Generator\Config\AbstractGeneratorConfig
     */
    public function getConfig(): AbstractGeneratorConfig
    {
        $this->config ??= new QuickGeneratorConfig();

        return $this->config;
    }

    /**
     * @return bool
     */
    public function isVfs(): bool
    {
        return !static::$disableVfs && $this->vfs;
    }

    /**
     * @param bool $vfs
     *
     * @return void
     */
    public function setVfs(bool $vfs): void
    {
        $this->vfs = $vfs;
    }

    /**
     * @param string $schemaXml
     * @param string|null $dsn
     * @param string|null $user
     * @param string|null $pass
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     * @param bool $vfs
     *
     * @return \Propel\Runtime\Connection\ConnectionWrapper
     */
    public static function buildSchema(
        string $schemaXml,
        ?string $dsn = null,
        ?string $user = null,
        ?string $pass = null,
        ?AdapterInterface $adapter = null,
        bool $vfs = true
    ): ConnectionWrapper {
        $builder = new self();
        $builder->setSchemaXml($schemaXml);
        $builder->setVfs($vfs);

        return $builder->build($dsn, $user, $pass, $adapter);
    }

    /**
     * Create Database object from schema.
     * Does not create classes or DB tables.
     *
     * @param string $schemaXml
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public static function parseSchema(string $schemaXml): ?Database
    {
        $builder = new self();
        $builder->setSchemaXml($schemaXml);

        return $builder->getDatabase();
    }

    /**
     * @return array<\Propel\Generator\Builder\Om\BuilderType>
     */
    public static function getDefaultClassTargets(): array
    {
        return [
            BuilderType::TableMap,

            BuilderType::Interface,

            BuilderType::ObjectBase,
            BuilderType::ObjectStub,

            BuilderType::Collection,

            BuilderType::QueryBase,
            BuilderType::QueryStub,

            BuilderType::Custom, // HACK: includes inheritance
        ];
    }

    /**
     * @param string|null $dsn
     * @param string|null $user
     * @param string|null $pass
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     * @param array<\Propel\Generator\Builder\Om\BuilderType>|null $classTargets
     *
     * @return \Propel\Runtime\Connection\ConnectionWrapper
     */
    public function build(
        ?string $dsn = null,
        ?string $user = null,
        ?string $pass = null,
        ?AdapterInterface $adapter = null,
        ?array $classTargets = null
    ): ConnectionWrapper {
        $dsn = $dsn ?? 'sqlite::memory:';
        $adapter = $adapter ?? new SqliteAdapter();
        $classTargets ??= static::getDefaultClassTargets();

        $pdo = new PdoConnection($dsn, $user, $pass, [], $adapter->getPdoSubclass());
        $con = new ConnectionWrapper($pdo);
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        /** @phpstan-var \Propel\Runtime\Adapter\Pdo\SqliteAdapter $adapter */
        $adapter->initConnection($con, []);
        $this->buildAndRunSql($con);
        $this->buildClasses($classTargets);
        $name = (string)$this->getDatabase()->getName();
        Perpl::getServiceContainer()->setAdapter($name, $adapter);
        Perpl::getServiceContainer()->setConnection($name, $con);

        return $con;
    }

    /**
     * @return \Propel\Generator\Model\Database
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            $schemaReader = new SchemaReader($this->getPlatform());
            $schemaReader->setGeneratorConfig($this->getConfig());
            $schema = $schemaReader->parseString($this->schemaXml);
            $this->database = $schema->getDatabase(); // does final initialization
        }
        assert($this->database !== null);

        return $this->database;
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @throws \Exception
     *
     * @return int The number of statements executed
     */
    public function buildAndRunSql(ConnectionInterface $con): int
    {
        $sql = $this->buildSql();
        $statements = SqlParser::parseString($sql);
        foreach ($statements as $statement) {
            if (strpos($statement, 'DROP') === 0) {
                // drop statements cause errors since the table doesn't exist
                continue;
            }
            try {
                $stmt = $con->prepare($statement);
                if ($stmt instanceof StatementInterface) {
                    // only execute if has no error
                    $stmt->execute();
                }
            } catch (Exception $e) {
                throw new Exception('SQL failed: ' . $statement, 0, $e);
            }
        }

        return count($statements);
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @throws \Propel\Generator\Exception\BuildException
     * @throws \RuntimeException
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public function updateDB(ConnectionInterface $con): ?Database
    {
        $database = $this->readConnectedDatabase();
        $diff = DatabaseComparator::computeDiff($database, $this->database);

        if ($diff === false) {
            return null;
        }
        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->database->getPlatform();
        $sql = $platform->buildModifyDatabaseDdl($diff);

        $statements = SqlParser::parseString($sql);
        foreach ($statements as $statement) {
            try {
                $stmt = $con->prepare($statement);

                if ($stmt === false) {
                    throw new RuntimeException('PdoConnection::prepare() failed and did not return statement object for execution.');
                }

                $stmt->execute();
            } catch (Exception $e) {
                //echo $sql; //uncomment for better debugging
                throw new BuildException(sprintf(
                    "Cannot execute SQL: \n%s\nFrom database: \n%s\n\nTo database: \n%s\n\nDiff:\n%s",
                    $statement,
                    $this->database,
                    $database,
                    $diff,
                ), $e->getCode(), $e);
            }
        }

        return $database;
    }

    /**
     * @return \Propel\Generator\Model\Database
     */
    public function readConnectedDatabase(): Database
    {
        $this->getDatabase();
        $database = new Database();
        $database->setSchema($this->database->getSchema());
        $database->setName($this->database->getName());
        $database->setPlatform($this->getPlatform());
        $this->getParser()->parse($database);

        return $database;
    }

    /**
     * @return string
     */
    public function buildSql(): string
    {
        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->getPlatform();

        return $platform->buildAddTablesDdl($this->getDatabase());
    }

    /**
     * @param array<\Propel\Generator\Builder\Om\BuilderType>|null $classTargets
     *
     * @return string
     */
    public function getBuildName(?array $classTargets = null): string
    {
        $firstThreeTables = array_slice($this->getDatabase()->getTables(), 0, 3);
        $tableNames = array_map(fn (Table $table) => $table->getName(), $firstThreeTables);
        $tableNamesString = implode('_', $tableNames);
        $targetsString = $classTargets === static::getDefaultClassTargets()
            ? 'all'
            : implode('_', array_map(fn ($t) => $t->value, $classTargets));

        return "$tableNamesString-$targetsString";
    }

    /**
     * Build the classes files and include them.
     *
     * When generated to virtual filesystem, the classes reside in a unique file. When they're are built to
     * physical filesystem, which is supposed to be for debugging purpose, the classes reside on separate file,
     * for easier debug.
     *
     * @param array<\Propel\Generator\Builder\Om\BuilderType>|null $classTargets
     *
     * @return void
     */
    public function buildClasses(?array $classTargets = null): void
    {
        $classTargets ??= static::getDefaultClassTargets();
        $tables = $this->getDatabase()->getTables();

        $includes = $this->isVfs()
            ? $this->buildClassesToVirtual($classTargets, $tables)
            : $this->buildClassesToPhysical($classTargets, $tables);

        foreach ($includes as $tempFile) {
            include $tempFile;
        }

        if (in_array(BuilderType::TableMap, $classTargets, true)) {
            $this->registerTableMaps();
        }
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param array<\Propel\Generator\Builder\Om\AbstractOMBuilder> $builders
     *
     * @return string|null
     */
    protected function buildPhpCodeForTableClasses(Table $table, array $builders): string|null
    {
        if (!$builders) {
            return null;
        }
        $script = '';
        foreach ($builders as $builder) {
            $code = $builder->build();
            $script .= $this->fixNamespaceDeclarations($code);
        }

        $script = str_replace('<?php', '', $script);

        return $script;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param array<\Propel\Generator\Builder\Om\BuilderType> $builderTypes
     *
     * @return array<\Propel\Generator\Builder\Om\AbstractOMBuilder>
     */
    protected function buildCodeBuilders(Table $table, array $builderTypes): array
    {
        $builders = [];
        foreach ($builderTypes as $builderType) {
            if ($builderType === BuilderType::Interface && !$table->getInterface()) {
                continue;
            }
            if ($builderType === BuilderType::Custom) {
                foreach ($table->getAdditionalBuilders() as $builderClass) {
                    $builders[] = new $builderClass($table);
                }

                // HACK: Inheritance code has to go into same file to match imports, do it during custom
                if ($table->usesSingleTableInheritance()) {
                    $builders = [...$builders, ...$this->buildInheritanceBuilders($table)];
                }

                continue;
            }

            $builders[] = $this->getConfig()->loadConfiguredBuilder($table, $builderType);
        }

        return $builders;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return array<\Propel\Generator\Builder\Om\AbstractOMBuilder&\Propel\Generator\Builder\Om\ExtensionBuilderInterface>
     */
    protected function buildInheritanceBuilders(Table $table): array
    {
        $classKeyColumn = $table->getClassKeyColumn();
        if (!$classKeyColumn) {
            return [];
        }

        $inheritanceBuilderTypes = [
            BuilderType::ObjectInheritanceStub,
            BuilderType::QueryInheritance,
            BuilderType::QueryInheritanceStub,
        ];

        $builders = [];
        foreach ($inheritanceBuilderTypes as $builderType) {
            foreach ($classKeyColumn->getInheritanceConfigurations() as $inheritance) {
                if (!$inheritance->getParentClassIdentifier()) {
                    continue;
                }

                /** @var \Propel\Generator\Builder\Om\AbstractOMBuilder&\Propel\Generator\Builder\Om\ExtensionBuilderInterface $builder */
                $builder = $this->getConfig()->loadConfiguredBuilder($table, $builderType);
                $builder->setInheritanceConfiguration($inheritance);
                $builders[] = $builder;
            }
        }

        return $builders;
    }

    /**
     * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/ClassLoader/ClassCollectionLoader.php
     *
     * @param string $source
     *
     * @return string
     */
    public function fixNamespaceDeclarations(string $source): string
    {
        $cooperativeLexems = [T_WHITESPACE, T_NS_SEPARATOR, T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED];

        $source = $this->forceNamespace($source);

        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        $inNamespace = false;
        $tokens = token_get_all($source);

        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT])) {
                // strip comments
                $output .= $token[1];
            } elseif ($token[0] === T_NAMESPACE) {
                if ($inNamespace) {
                    $output .= "}\n";
                }
                $output .= $token[1];

                // namespace name and whitespaces
                while (($t = $tokens[++$i]) && is_array($t) && in_array($t[0], $cooperativeLexems)) {
                    $output .= $t[1];
                }
                if ($t === '{') {
                    $inNamespace = false;
                    --$i;
                } else {
                    $output .= "\n{";
                    $inNamespace = true;
                }
            } else {
                $output .= $token[1];
            }
        }

        if ($inNamespace) {
            $output .= "}\n";
        }

        return $output;
    }

    /**
     * Prevent generated class without namespace to fail.
     *
     * @param string $code
     *
     * @return string
     */
    protected function forceNamespace(string $code): string
    {
        if (preg_match('/\nnamespace/', $code) === 0) {
            $lines = explode(PHP_EOL, $code);
            $isUseStatement = fn (string $s) => str_starts_with($s, 'use ') && !str_contains($s, '\\');
            $useStatements = array_filter($lines, $isUseStatement);

            $code = str_replace($useStatements, '', $code);

            return "\nnamespace\n{\n{$code}\n}\n";
        }

        return $code;
    }

    /**
     * @return bool
     */
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuoting;
    }

    /**
     * @param bool $identifierQuoting
     *
     * @return void
     */
    public function setIdentifierQuoting(bool $identifierQuoting): void
    {
        $this->identifierQuoting = $identifierQuoting;
    }

    /**
     * Create separate classes to write to physical filesystem.
     *
     * @param array<\Propel\Generator\Builder\Om\BuilderType> $builderTypes
     * @param array<\Propel\Generator\Model\Table> $tables Array of Table objects
     *
     * @return array<string> The files to include
     */
    private function buildClassesToPhysical(array $builderTypes, array $tables): array
    {
        $includes = [];
        foreach ($tables as $table) {
            $targetDir = $this->createTargetDir($table);
            $cleanedTableName = str_replace('\\', '-', $table->getPhpName());

            foreach ($builderTypes as $builderType) {
                $builders = $this->buildCodeBuilders($table, [$builderType]);
                $code = $builders ? $this->buildPhpCodeForTableClasses($table, $builders) : null;
                if (!$code) {
                    continue;
                }

                $tempFileName = "{$targetDir}{$cleanedTableName}-{$builderType->value}.php";
                file_put_contents($tempFileName, "<?php\n" . $code);
                $includes[] = $tempFileName;
            }
        }

        return $includes;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    protected function createTargetDir(Table $table): string
    {
        $namespace = $table->getNamespace() ?: 'root';
        $namespaceDir = str_replace('\\', '__', $namespace);
        $version = Perpl::VERSION;
        $dirName = sys_get_temp_dir() . "/perplQuickBuild/v{$version}/$namespaceDir/";
        if (!is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }

        return $dirName;
    }

    /**
     * Create an all-classes file to write to virtual filesystem.
     *
     * @param array<\Propel\Generator\Builder\Om\BuilderType> $targets
     * @param array<\Propel\Generator\Model\Table> $tables Array of Table objects
     *
     * @return array<string> The one element array, containing the file to include
     */
    private function buildClassesToVirtual(array $targets, array $tables): array
    {
        $allCode = '';
        foreach ($tables as $table) {
            $builders = $this->buildCodeBuilders($table, $targets);
            $allCode .= $this->buildPhpCodeForTableClasses($table, $builders) ?? '';
        }

        $firstFiveTables = array_slice($tables, 0, 5);
        $tableNames = array_map(fn (Table $t) => $t->getPhpName(), $firstFiveTables);
        $fileId = implode('_', $tableNames);

        $tempFile = $this->newFile("perplQuickBuild/$fileId.php");
        file_put_contents($tempFile->url(), "<?php\n$allCode");

        return [$tempFile->url()];
    }

    /**
     * @return void
     */
    protected function registerTableMaps(): void
    {
        $serviceContainer = Perpl::getServiceContainer();
        $serviceContainer->initDatabaseMaps();
        $db = $this->getDatabase();
        $dbName = $db->getName();
        $dbMap = $serviceContainer->getDatabaseMap($dbName);
        $builder = new TableMapBuilder(new Table(''));
        $builder->setGeneratorConfig($this->config);
        foreach ($db->getTables() as $table) {
            $builder->setTable($table);
            /** @phpstan-var class-string<\Propel\Runtime\Map\TableMap> $mapClass */
            $mapClass = $builder->getFullyQualifiedClassName();
            $dbMap->addTableFromMapClass($mapClass);
        }
    }
}
