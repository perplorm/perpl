<?php

declare(strict_types = 1);

namespace Propel\Generator\Reverse;

use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\VendorInfo;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use RuntimeException;
use function assert;

/**
 * Base class for reverse engineering a database schema.
 */
abstract class AbstractSchemaParser implements SchemaParserInterface
{
    protected ConnectionInterface|null $con = null;

    /**
     * Stack of warnings.
     *
     * @var list<string>
     */
    protected array $warnings = [];

    /**
     * GeneratorConfig object holding build properties.
     */
    private AbstractGeneratorConfig|null $generatorConfig = null;

    /**
     * Map native DB types to Propel types.
     * (Override in subclasses.)
     *
     * @var array<\Propel\Generator\Model\Datatype\ColumnType>|null
     */
    protected array|null $nativeToPropelTypeMap = null;

    protected string $migrationTableName = 'propel_migration';

    protected PlatformInterface|null $platform = null;

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional database connection
     */
    public function __construct(?ConnectionInterface $con = null)
    {
        if ($con) {
            $this->setConnection($con);
        }
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @return void
     */
    #[\Override]
    public function setConnection(ConnectionInterface $con): void
    {
        $this->con = $con;
    }

    /**
     * Gets the database connection.
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    #[\Override]
    public function getConnection(): ConnectionInterface
    {
        assert($this->con !== null);

        return $this->con;
    }

    /**
     * @param string $migrationTableName
     *
     * @return void
     */
    public function setMigrationTable(string $migrationTableName): void
    {
        $this->migrationTableName = $migrationTableName;
    }

    /**
     * @return string
     */
    public function getMigrationTable(): string
    {
        return $this->migrationTableName;
    }

    /**
     * Pushes a message onto the stack of warnings.
     *
     * @param string $msg
     *
     * @return void
     */
    protected function warn(string $msg): void
    {
        $this->warnings[] = $msg;
    }

    /**
     * @return array<string>
     */
    #[\Override]
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Sets the GeneratorConfig to use in the parsing.
     *
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $config
     *
     * @return void
     */
    #[\Override]
    public function setGeneratorConfig(AbstractGeneratorConfig $config): void
    {
        $this->generatorConfig = $config;
    }

    /**
     * @return \Propel\Generator\Config\AbstractGeneratorConfig|null
     */
    public function getGeneratorConfig(): ?AbstractGeneratorConfig
    {
        return $this->generatorConfig;
    }

    /**
     * Gets a type mapping from native type to column type.
     *
     * @return array<\Propel\Generator\Model\Datatype\ColumnType>
     */
    abstract protected function buildTypeMapping(): array;

    /**
     * Gets a mapped Propel type for specified native type.
     *
     * @param string $nativeType
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType|null The mapped Propel type.
     */
    protected function getMappedPropelType(string $nativeType): ?ColumnType
    {
        $this->nativeToPropelTypeMap ??= $this->buildTypeMapping();

        return $this->nativeToPropelTypeMap[$nativeType] ?? null;
    }

    /**
     * Gets a new VendorInfo object for this platform with specified params.
     *
     * @param array $params
     *
     * @return \Propel\Generator\Model\VendorInfo
     */
    protected function createVendorInfoObject(array $params): VendorInfo
    {
        $type = $this->getPlatform()->getDatabaseType();

        $vi = new VendorInfo($type);
        $vi->setParameters($params);

        return $vi;
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return void
     */
    #[\Override]
    public function setPlatform(PlatformInterface $platform): void
    {
        $this->platform = $platform;
    }

    /**
     * @return bool
     */
    public function hasPlatform(): bool
    {
        return $this->platform !== null;
    }

    /**
     * @throws \RuntimeException
     *
     * @return \Propel\Generator\Platform\PlatformInterface
     */
    #[\Override]
    public function getPlatform(): PlatformInterface
    {
        $this->platform ??= $this->getGeneratorConfig()->getConfiguredPlatform();

        if (!$this->platform) {
            throw new RuntimeException('No platform set, please use `hasPlatform()` to check for existence first.');
        }

        return $this->platform;
    }
}
