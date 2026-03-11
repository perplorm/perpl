<?php

declare(strict_types = 1);

namespace Propel\Runtime\Connection;

use Propel\Runtime\Adapter\AdapterInterface;

interface ConnectionManagerInterface
{
    /**
     * @param string $name The datasource name associated to this connection
     *
     * @return void
     */
    public function setName(string $name): void;

    /**
     * @return string The datasource name associated to this connection
     */
    public function getName(): string;

    /**
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    public function getWriteConnection(?AdapterInterface $adapter = null): ConnectionInterface;

    /**
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    public function getReadConnection(?AdapterInterface $adapter = null): ConnectionInterface;

    /**
     * @return void
     */
    public function closeConnections(): void;
}
