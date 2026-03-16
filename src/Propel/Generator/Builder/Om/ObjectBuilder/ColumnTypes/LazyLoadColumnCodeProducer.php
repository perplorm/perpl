<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\OraclePlatform;
use Propel\Generator\Platform\SqlsrvPlatform;

class LazyLoadColumnCodeProducer extends ColumnCodeProducer
{
    /**
     * @var string
     */
    protected const IS_LOADED_ATTRIBUTE_SUFFIX = '_is_loaded';

    /**
     * @var \Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer
     */
    protected ColumnCodeProducer $columnCodeProducer;

    /**
     * @param \Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer $columnCodeProducer
     */
    public function __construct(ColumnCodeProducer $columnCodeProducer)
    {
        $this->columnCodeProducer = $columnCodeProducer;
        parent::__construct($columnCodeProducer->column, $columnCodeProducer->builder);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Config\AbstractGeneratorConfig|null $generatorConfig
     *
     * @return void
     */
    #[\Override]
    protected function init(Table $table, ?AbstractGeneratorConfig $generatorConfig): void
    {
        parent::init($table, $generatorConfig);
        $this->columnCodeProducer->init($table, $generatorConfig);
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    protected function getIsLoadedAttributeName(string $prefix = '$this->'): string
    {
        return $this->getAttributeName($prefix, static::IS_LOADED_ATTRIBUTE_SUFFIX);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addColumnAttributes(string &$script): void
    {
        $this->columnCodeProducer->addColumnAttributes($script);
        $this->addColumnAttributeLoaderDeclaration($script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addColumnAttributeLoaderDeclaration(string &$script): void
    {
        $clo = $this->column->getLowercasedName();
        $isLoadedAttribute = $this->getIsLoadedAttributeName('$');

        $script .= "
    /**
     * Whether the lazy-loaded \$$clo value has been loaded from database.
     * This is necessary to avoid repeated lookups if \$$clo column is NULL in the db.
     */
    protected bool $isLoadedAttribute = false;\n";
    }

    /**
     * Build statement used in Model::clear()
     *
     * @see ObjectBuilder::addClear()}
     *
     * @return string
     */
    #[\Override]
    public function getClearValueStatement(): string
    {
        $isLoadedAttribute = $this->getIsLoadedAttributeName();

        return $this->columnCodeProducer->getClearValueStatement() . "
        $isLoadedAttribute = false;";
    }

    /**
     * Build statement used in Model::reload()
     *
     * @see ObjectBuilder::addReload()}
     *
     * @return string
     */
    public function getReloadStatement(): string
    {
        $isLoadedAttribute = $this->getIsLoadedAttributeName();
        $fieldAttribute = $this->getAttributeName();

        return "
        $fieldAttribute = null;
        $isLoadedAttribute = false;\n";
    }

    /**
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        return $this->columnCodeProducer->getDefaultValueString();
    }

    /**
     * Add the comment for a default accessor method (a getter).
     *
     * @param string $script
     * @param string $additionalParam injected from outer class (lazy load)
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorComment(string &$script, string $additionalParam = ''): void
    {
        $additionalParam = "
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con An optional ConnectionInterface connection to use for fetching this lazy-loaded column.{$additionalParam}";

        $this->columnCodeProducer->addAccessorComment($script, $additionalParam);
    }

    /**
     * Adds the function declaration for a default accessor.
     *
     * @param string $script
     * @param string $additionalParam injected from outer class (lazy load)
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorOpen(string &$script, string $additionalParam = ''): void
    {
        $conParam = '?ConnectionInterface $con = null';
        $additionalParam = $additionalParam ? "$additionalParam, $conParam" : $conParam;

        $this->columnCodeProducer->addAccessorOpen($script, $additionalParam);
    }

    /**
     * Adds the function body for a default accessor method.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorBody(string &$script): void
    {
        $script .= $this->getAccessorLazyLoadSnippet();
        $this->columnCodeProducer->addAccessorBody($script);
    }

    /**
     * Gets accessor lazy loaded snippets.
     *
     * @return string
     */
    protected function getAccessorLazyLoadSnippet(): string
    {
        $fieldAttribute = $this->getAttributeName();
        $isLoadedAttribute = $this->getIsLoadedAttributeName();
        $defaultValueString = $this->getDefaultValueString();
        $callLoad = 'load' . $this->column->getPhpName();

        return "
        if (!$isLoadedAttribute && $fieldAttribute === $defaultValueString && !\$this->isNew()) {
            \$this->$callLoad(\$con);
        }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addAccessorAddition(string &$script): void
    {
        $this->addLazyLoader($script);
    }

    /**
     * Adds the lazy loader method.
     *
     * @param string $script
     *
     * @return void
     */
    protected function addLazyLoader(string &$script): void
    {
        $this->addLazyLoaderComment($script);
        $this->addLazyLoaderOpen($script);
        $this->addLazyLoaderBody($script);
        $this->addLazyLoaderClose($script);
    }

    /**
     * Adds the comment for the lazy loader method.
     *
     * @param string $script
     *
     * @return void
     */
    protected function addLazyLoaderComment(string &$script): void
    {
        $clo = $this->column->getLowercasedName();

        $script .= "
    /**
     * Load the value for the lazy-loaded [$clo] column.
     *
     * This method performs an additional query to return the value for
     * the [$clo] column, since it is not populated by
     * the hydrate() method.
     *
     * @param \$con ConnectionInterface (optional) The ConnectionInterface connection to use.
     *
     * @throws \Propel\Runtime\Exception\PropelException - any underlying error will be wrapped and re-thrown.
     *
     * @return void
     */";
    }

    /**
     * Adds the function declaration for the lazy loader method.
     *
     * @param string $script
     *
     * @return void
     */
    protected function addLazyLoaderOpen(string &$script): void
    {
        $cfc = $this->column->getPhpName();
        $script .= "
    protected function load$cfc(?ConnectionInterface \$con = null)
    {";
    }

    /**
     * Adds the function body for the lazy loader method.
     *
     * @param string $script
     *
     * @return void
     */
    protected function addLazyLoaderBody(string &$script): void
    {
        $this->declareGlobalFunction('current');
        $platform = $this->getPlatform();
        $fieldAttribute = $this->getAttributeName();
        $isLoadedAttribute = $this->getIsLoadedAttributeName();
        $columnConstant = $this->builder->getColumnConstant($this->column);
        $queryClassName = $this->getQueryClassName();
        $clo = $this->column->getLowercasedName();

        // pdo_sqlsrv driver requires the use of PDOStatement::bindColumn() or a hex string will be returned
        if ($this->column->getType() === PropelTypes::BLOB && $platform instanceof SqlsrvPlatform) {
            $script .= "
        \$c = \$this->buildPkeyCriteria();
        \$c->addSelectColumn($columnConstant);
        try {
            \$row = [0 => null];
            \$dataFetcher = {$queryClassName}::create(null, \$c)->fetch(\$con);
            if (\$dataFetcher instanceof PDODataFetcher) {
                \$dataFetcher->bindColumn(1, \$row[0], PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
            }
            \$row = \$dataFetcher->fetch(PDO::FETCH_BOUND);
            \$dataFetcher->close();";
        } else {
            $script .= "
        \$c = \$this->buildPkeyCriteria();
        \$c->addSelectColumn($columnConstant);
        try {
            \$dataFetcher = {$queryClassName}::create(null, \$c)->fetch(\$con);
            \$row = \$dataFetcher->fetch();
            \$dataFetcher->close();";
        }

        $script .= "\n
            \$firstColumn = is_bool(\$row) ? null : current(\$row);\n";

        if ($this->column->getType() === PropelTypes::CLOB && $platform instanceof OraclePlatform) {
            // PDO_OCI returns a stream for CLOB objects, while other PDO adapters return a string...
            $this->declareGlobalFunction('stream_get_contents');
            $script .= "
            if (\$firstColumn) {
                $fieldAttribute = stream_get_contents(\$firstColumn);
            }";
        } elseif ($this->column->isLobType() && !$platform->hasStreamBlobImpl()) {
            $script .= "
            $fieldAttribute = \$this->writeResource(\$firstColumn);";
        } elseif ($this->column->isPhpPrimitiveType()) {
            $phpType = $this->column->getPhpType();
            $script .= "
            $fieldAttribute = (\$firstColumn !== null) ? ($phpType)\$firstColumn : null;";
        } elseif ($this->column->isPhpObjectType()) {
            $phpType = $this->column->getPhpType();
            $script .= "
            $fieldAttribute = (\$firstColumn !== null) ? new $phpType(\$firstColumn) : null;";
        } elseif ($this->column->getType() === PropelTypes::UUID_BINARY) {
            $uuidSwapFlag = $this->builder->getUuidSwapFlagLiteral();
            $this->declareGlobalFunction('is_resource', 'stream_get_contents');
            $script .= "
            if (is_resource(\$firstColumn)) {
                \$firstColumn = stream_get_contents(\$firstColumn);
            }
            $fieldAttribute = UuidConverter::binToUuid(\$firstColumn, $uuidSwapFlag);";
        } else {
            $script .= "
            $fieldAttribute = \$firstColumn;";
        }

        $script .= "
            $isLoadedAttribute = true;
        } catch (Exception \$e) {
            throw new PropelException('Error loading value for [$clo] column on demand.', 0, \$e);
        }";
    }

    /**
     * Adds the function close for the lazy loader.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addLazyLoaderClose(string &$script): void
    {
        $script .= "
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorComment(string &$script): void
    {
        $this->columnCodeProducer->addMutatorComment($script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorMethodHeader(string &$script): void
    {
        $this->columnCodeProducer->addMutatorMethodHeader($script);
    }

    /**
     * Adds the mutator open body part.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addMutatorBody(string &$script): void
    {
        $cfc = $this->column->getPhpName();
        $isLoadedAttribute = $this->getIsLoadedAttributeName();
        $script .= "
        // explicitly set the is-loaded flag to true for this lazy load col;
        // it doesn't matter if the value is actually set or not (logic below) as
        // any attempt to set the value means that no db lookup should be performed
        // when the get$cfc() method is called.
        $isLoadedAttribute = true;\n";

        $this->columnCodeProducer->addMutatorBody($script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorBodyRelationsCode(string &$script): void
    {
        $this->columnCodeProducer->addMutatorBodyRelationsCode($script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorAddition(string &$script): void
    {
        $this->columnCodeProducer->addMutatorAddition($script);
    }
}
