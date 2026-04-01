<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager;

use Closure;
use DOMDocument;
use Exception;
use LogicException;
use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Schema;
use Propel\Generator\Platform\PlatformInterface;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use XSLTProcessor;
use function array_shift;
use function class_exists;
use function count;
use function dirname;
use function file;
use function in_array;
use function is_readable;
use function realpath;
use function sprintf;
use function strpos;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

/**
 * An abstract base Propel manager to perform work related to the XML schema
 * file.
 *
 * Requires PHP XSL extension for XSLT transformations.
 */
abstract class AbstractManager
{
    /**
     * Data models that we collect. One from each XML schema file.
     *
     * @var list<\Propel\Generator\Model\Schema>
     */
    protected $dataModels = [];

    /**
     * @var array<\Propel\Generator\Model\Database>
     */
    protected $databases;

    /**
     * Map of data model name to database name.
     * Should probably stick to the convention
     * of them being the same but I know right now
     * in a lot of cases they won't be.
     *
     * @var array
     */
    protected $dataModelDbMap;

    /**
     * DB encoding to use for SchemaReader object
     *
     * @var string
     */
    protected $dbEncoding = 'UTF-8';

    /**
     * Whether to perform validation (XSD) on the schema.xml file(s).
     *
     * @var bool
     */
    protected $validate = false;

    /**
     * The XSD schema file to use for validation.
     *
     * @var string
     */
    protected $xsd;

    /**
     * XSL file to use to normalize (or otherwise transform) schema before validation.
     *
     * @var mixed
     */
    protected $xsl;

    /**
     * Gets list of all used xml schemas
     *
     * @var array<\Symfony\Component\Finder\SplFileInfo>
     */
    protected $schemas = [];

    /**
     * @var string
     */
    protected $workingDirectory;

    /**
     * @var \Closure|null
     */
    private $loggerClosure;

    /**
     * Have datamodels been initialized?
     *
     * @var bool
     */
    private $dataModelsLoaded = false;

    /**
     * An initialized GeneratorConfig object.
     *
     * @var \Propel\Generator\Config\AbstractGeneratorConfig
     */
    private $generatorConfig;

    /**
     * Returns the list of schemas.
     *
     * @return array<\Symfony\Component\Finder\SplFileInfo>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Sets the schemas list.
     *
     * @param array<\Symfony\Component\Finder\SplFileInfo> $schemas
     *
     * @return void
     */
    public function setSchemas(array $schemas): void
    {
        $this->schemas = $schemas;
    }

    /**
     * Sets the working directory path.
     *
     * @param string $workingDirectory
     *
     * @return void
     */
    public function setWorkingDirectory(string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Returns the working directory path.
     *
     * @return string|null
     */
    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    /**
     * Returns the data models that have been
     * processed.
     *
     * @return array<\Propel\Generator\Model\Schema>
     */
    public function getDataModels(): array
    {
        if (!$this->dataModelsLoaded) {
            $this->loadDataModels();
        }

        return $this->dataModels;
    }

    /**
     * Returns the data model to database name map.
     *
     * @return array
     */
    public function getDataModelDbMap(): array
    {
        if (!$this->dataModelsLoaded) {
            $this->loadDataModels();
        }

        return $this->dataModelDbMap;
    }

    /**
     * @return array<\Propel\Generator\Model\Database>
     */
    public function getDatabases(): array
    {
        if ($this->databases === null) {
            /** @var array<\Propel\Generator\Model\Database> $databases */
            $databases = [];
            foreach ($this->getDataModels() as $dataModel) {
                foreach ($dataModel->getDatabases() as $database) {
                    $databaseName = $database->getName();
                    $existingDatabase = $databases[$databaseName] ?? null;
                    if (!$existingDatabase) {
                        $databases[$databaseName] = $database;
                    } else {
                        $newTables = $database->getTables();
                        foreach ($newTables as $newTable) {
                            if ($existingDatabase->hasTable($newTable->getName(), true)) {
                                continue;
                            }
                            $existingDatabase->addTable($newTable);
                        }
                    }
                }
            }
            $this->databases = $databases;
        }

        return $this->databases;
    }

    /**
     * @param string $name
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public function getDatabase(string $name): ?Database
    {
        $dbs = $this->getDatabases();

        return $dbs[$name] ?? null;
    }

    /**
     * Sets whether to perform validation on the datamodel schema.xml file(s).
     *
     * @param bool $validate
     *
     * @return void
     */
    public function setValidate(bool $validate): void
    {
        $this->validate = $validate;
    }

    /**
     * Sets the XSD schema to use for validation of any datamodel schema.xml
     * file(s).
     *
     * @param string $xsd
     *
     * @return void
     */
    public function setXsd(string $xsd): void
    {
        $this->xsd = $xsd;
    }

    /**
     * Sets the normalization XSLT to use to transform datamodel schema.xml
     * file(s) before validation and parsing.
     *
     * @param mixed $xsl
     *
     * @return void
     */
    public function setXsl($xsl): void
    {
        $this->xsl = $xsl;
    }

    /**
     * Sets the current target database encoding.
     *
     * @param string $encoding Target database encoding
     *
     * @return void
     */
    public function setDbEncoding(string $encoding): void
    {
        $this->dbEncoding = $encoding;
    }

    /**
     * Sets a logger closure.
     *
     * @param \Closure $logger
     *
     * @return void
     */
    public function setLoggerClosure(Closure $logger): void
    {
        $this->loggerClosure = $logger;
    }

    /**
     * Returns all matching XML schema files and loads them into data models for
     * class.
     *
     * @throws \Propel\Generator\Exception\BuildException
     *
     * @return void
     */
    protected function loadDataModels(): void
    {
        $schemas = [];
        $totalNbTables = 0;
        $dataModelFiles = $this->getSchemas();
        $defaultPlatform = $this->getGeneratorConfig()->getConfiguredPlatform();

        foreach ($dataModelFiles as $schemaFile) {
            $schema = $this->processSchemaFile($schemaFile, $defaultPlatform);
            if (!$schema) {
                continue;
            }
            $schemas[] = $schema;

            $nbTables = $schema->getDatabase(null, false)->countTables();
            $totalNbTables += $nbTables;
            $this->log("  $nbTables tables processed successfully in {$schemaFile->getPathname()}");
        }

        $this->log(sprintf('%d tables found in %d schema files.', $totalNbTables, count($dataModelFiles)));

        if (!$schemas) {
            throw new BuildException('No schema files were found (matching your schema fileset definition).');
        }

        foreach ($schemas as $schema) {
            // map schema filename with database name
            $this->dataModelDbMap[$schema->getName()] = $schema->getDatabase(null, false)->getName();
        }

        if (count($schemas) > 1 && $this->getGeneratorConfig()->getConfigProperty('generator.packageObjectModel')) {
            $schema = $this->joinDataModels($schemas);
            $this->dataModels = [$schema];
        } else {
            $this->dataModels = $schemas;
        }

        foreach ($this->dataModels as &$schema) {
            $schema->doFinalInitialization();
        }

        $this->dataModelsLoaded = true;
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo $schemaFile
     * @param \Propel\Generator\Platform\PlatformInterface $defaultPlatform
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return \Propel\Generator\Model\Schema|null
     */
    protected function processSchemaFile(SplFileInfo $schemaFile, PlatformInterface $defaultPlatform): Schema|null
    {
        $dmFilename = $schemaFile->getPathname();
        $this->log('Processing: ' . $schemaFile->getFilename());

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->load($dmFilename);

        $this->includeExternalSchemas($dom, $schemaFile->getPath());

        // normalize/transform XML document using XSLT
        if ($this->getGeneratorConfig()->getConfigProperty('generator.schema.transform') && $this->xsl) {
            $this->log('Transforming ' . $dmFilename . ' using stylesheet ' . $this->xsl->getPath());
            $this->applyXlsTransformation($dom);
        }

        // validate the XML document using XSD schema
        if ($this->validate && $this->xsd) {
            $this->log('  Validating XML using schema ' . $this->xsd);

            if (!$dom->schemaValidate($this->xsd)) {
                throw new EngineException("XML schema file ($dmFilename) does not validate. See warnings above for reasons validation failed (make sure error_reporting is set to show E_WARNING if you don't see any).");
            }
        }

        $xmlParser = new SchemaReader($defaultPlatform, $this->dbEncoding);
        $xmlParser->setGeneratorConfig($this->getGeneratorConfig());
        $schema = $xmlParser->parseString((string)$dom->saveXML(), $dmFilename);
        $schema?->setName($dmFilename);

        return $schema;
    }

    /**
     * @param \DOMDocument $dom
     *
     * @throws \RuntimeException
     *
     * @return \DOMDocument
     */
    protected function applyXlsTransformation(DOMDocument $dom): DOMDocument
    {
        if (!class_exists('\XSLTProcessor')) {
            $this->log('Skipping XLST transformation. Make sure PHP has been compiled/configured to support XSLT.');

            return $dom;
        }

        // normalize the document using normalizer stylesheet
        $xslDom = new DOMDocument('1.0', 'UTF-8');
        $xslDom->load($this->xsl->getAbsolutePath());
        $xsl = new XSLTProcessor();
        $xsl->importStylesheet($xslDom);
        $dom = $xsl->transformToDoc($dom);

        if ($dom === false) {
            throw new RuntimeException('XSLTProcessor transformation to a DOMDocument failed.');
        }

        return $dom;
    }

    /**
     * Replaces all external-schema nodes with the content of XML schema that node refers to
     *
     * Recurses to include any external schema referenced from in an included XML (and deeper)
     * Note: this function very much assumes at least a reasonable XML schema, maybe it'll proof
     * users don't have those and adding some more informative exceptions would be better
     *
     * @param \DOMDocument $dom
     * @param string $srcDir
     *
     * @throws \Propel\Generator\Exception\BuildException
     *
     * @return int number of included external schemas
     */
    protected function includeExternalSchemas(DOMDocument $dom, string $srcDir): int
    {
        $databaseNode = $dom->getElementsByTagName('database')->item(0);
        $externalSchemaNodes = $dom->getElementsByTagName('external-schema');

        $nbIncludedSchemas = 0;
        while ($externalSchema = $externalSchemaNodes->item(0)) {
            $filePath = $externalSchema->getAttribute('filename');
            $referenceOnly = $externalSchema->getAttribute('referenceOnly');
            $this->log('Processing external schema: ' . $filePath);

            $externalSchema->parentNode->removeChild($externalSchema);

            $externalSchemaPath = realpath($srcDir . DIRECTORY_SEPARATOR . $filePath);
            if ($externalSchemaPath === false) {
                $externalSchemaPath = realpath($filePath);
            }
            if ($externalSchemaPath === false || !is_readable($externalSchemaPath)) {
                throw new BuildException("Cannot read external schema at '$filePath'");
            }

            $externalSchemaDom = new DOMDocument('1.0', 'UTF-8');
            $externalSchemaDom->load($externalSchemaPath);

            $this->includeExternalSchemas($externalSchemaDom, dirname($externalSchemaPath));
            foreach ($externalSchemaDom->getElementsByTagName('table') as $tableNode) {
                if ($referenceOnly === 'true') {
                    $tableNode->setAttribute('skipSql', 'true');
                }
                $databaseNode->appendChild($dom->importNode($tableNode, true));
            }

            $nbIncludedSchemas++;
        }

        return $nbIncludedSchemas;
    }

    /**
     * Joins the datamodels collected from schema.xml files into one big datamodel.
     * We need to join the datamodels in this case to allow for foreign keys
     * that point to tables in different packages.
     *
     * @param array<\Propel\Generator\Model\Schema> $schemas
     *
     * @throws \LogicException
     *
     * @return \Propel\Generator\Model\Schema
     */
    protected function joinDataModels(array $schemas): Schema
    {
        $mainSchema = array_shift($schemas);
        if (!$mainSchema) {
            throw new LogicException('Cannot join data models of empty schemas');
        }
        $mainSchema->joinSchemas($schemas);

        return $mainSchema;
    }

    /**
     * Returns the GeneratorConfig object for this manager or creates it
     * on-demand.
     *
     * @return \Propel\Generator\Config\AbstractGeneratorConfig
     */
    protected function getGeneratorConfig(): AbstractGeneratorConfig
    {
        return $this->generatorConfig;
    }

    /**
     * Sets the GeneratorConfigInterface implementation.
     *
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @return void
     */
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void
    {
        $this->generatorConfig = $generatorConfig;
    }

    /**
     * @throws \Propel\Generator\Exception\BuildException
     *
     * @return void
     */
    protected function validate(): void
    {
        if ($this->validate) {
            if (!$this->xsd) {
                throw new BuildException("'validate' set to TRUE, but no XSD specified (use 'xsd' attribute).");
            }
        }
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected function log(string $message): void
    {
        if ($this->loggerClosure !== null) {
            $closure = $this->loggerClosure;
            $closure($message);
        }
    }

    /**
     * Returns an array of properties as key/value pairs from an input file.
     *
     * @param string $file
     *
     * @throws \Exception
     *
     * @return array<string>
     */
    protected function getProperties(string $file): array
    {
        $properties = [];

        $lines = @file($file);
        if ($lines === false) {
            throw new Exception(sprintf('Unable to parse contents of "%s".', $file));
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if (!$line || in_array($line[0], ['#', ';'], true)) {
                continue;
            }

            $length = strpos($line, '=') ?: null;
            $properties[trim(substr($line, 0, $length))] = trim(substr($line, $length + 1));
        }

        return $properties;
    }
}
