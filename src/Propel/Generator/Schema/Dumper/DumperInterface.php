<?php

declare(strict_types = 1);

namespace Propel\Generator\Schema\Dumper;

use Propel\Generator\Model\Database;
use Propel\Generator\Model\Schema;

interface DumperInterface
{
    /**
     * Dumps a Database model into a text formatted version.
     *
     * @param \Propel\Generator\Model\Database $database The database model
     *
     * @return string The dumped formatted output (XML, YAML, CSV...)
     */
    public function dump(Database $database): string;

    /**
     * Dumps a single Schema model into an XML formatted version.
     *
     * @param \Propel\Generator\Model\Schema $schema The schema model
     * @param bool $doFinalInitialization Whether to validate the schema
     *
     * @return string The dumped formatted output (XML, YAML, CSV...)
     */
    public function dumpSchema(Schema $schema, bool $doFinalInitialization = true): string;
}
