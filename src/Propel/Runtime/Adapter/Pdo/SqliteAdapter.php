<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\Adapter\Pdo;

use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Lock;
use Propel\Runtime\Adapter\SqlAdapterInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;
use RuntimeException;
use function class_exists;
use function get_class;
use function mb_ereg;
use function mb_regex_encoding;
use function method_exists;
use function phpversion;
use function sprintf;

/**
 * This is used in order to connect to a SQLite database.
 *
 * @author Hans Lellelid <hans@xmpl.org>
 */
class SqliteAdapter extends PdoAdapter implements SqlAdapterInterface
{
    /**
     * @return class-string<\PDO>
     */
    #[\Override]
    public function getPdoSubclass(): string
    {
        /** @var class-string<\PDO> $class */
        $class = class_exists('\PDO\Sqlite') ? '\PDO\Sqlite' : PDO::class;

        return $class;
    }

    /**
     * For SQLite this method has no effect, since SQLite doesn't support specifying a character
     * set (or, another way to look at it, it doesn't require a single character set per DB).
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface $con A PDO connection instance.
     * @param string $charset The charset encoding.
     *
     * @return void
     */
    #[\Override]
    public function setCharset(ConnectionInterface $con, string $charset): void
    {
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     * @param array $settings
     *
     * @return void
     */
    #[\Override]
    public function initConnection(ConnectionInterface $con, array $settings): void
    {
        $con->query('PRAGMA foreign_keys = ON');
        parent::initConnection($con, $settings);

        //add regex support
        $this->createFunction($con, 'regexp', function ($pattern, $value) {
            mb_regex_encoding('UTF-8');

            return (mb_ereg($pattern, $value) !== false) ? 1 : 0;
        });
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     * @param string $functionName
     * @param callable $callback
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function createFunction(ConnectionInterface $con, string $functionName, callable $callback): void
    {
        $unwrappedConnection = $con instanceof ConnectionWrapper ? $con->getWrappedConnection() : $con;
        if (!$unwrappedConnection instanceof PdoConnection) {
            throw new RuntimeException('Expected PDO connection but got ' . get_class($con));
        }
        $pdo = $unwrappedConnection->getPdo();

        if (method_exists($pdo, 'createFunction')) {
            $createFunction = 'createFunction'; // $pdo is \PDO\Sqlite (available since PH 8.4)
        } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
            $createFunction = 'sqliteCreateFunction'; // $pdo is PDO before PHP 8.5
        } else {
            throw new RuntimeException(sprintf('Cannot create function on PDO class %s (PHP %s)', get_class($con), phpversion()));
        }

        $pdo->$createFunction($functionName, $callback);
    }

    /**
     * Returns SQL which concatenates the second string to the first.
     *
     * @param string $s1 String to concatenate.
     * @param string $s2 String to append.
     *
     * @return string
     */
    #[\Override]
    public function concatString(string $s1, string $s2): string
    {
        return "($s1 || $s2)";
    }

    /**
     * Returns SQL which extracts a substring.
     *
     * @param string $s String to extract from.
     * @param int $pos Offset to start from.
     * @param int $len Number of characters to extract.
     *
     * @return string
     */
    #[\Override]
    public function subString(string $s, int $pos, int $len): string
    {
        return "substr($s, $pos, $len)";
    }

    /**
     * Returns SQL which calculates the length (in chars) of a string.
     *
     * @param string $s String to calculate length of.
     *
     * @return string
     */
    #[\Override]
    public function strLength(string $s): string
    {
        return "length($s)";
    }

    /**
     * @see \Propel\Runtime\Adapter\AdapterInterface::quoteIdentifier()
     *
     * @param string $text
     *
     * @return string
     */
    #[\Override]
    public function quoteIdentifier(string $text): string
    {
        return "[$text]";
    }

    /**
     * @see SqlAdapterInterface::applyLimit()
     *
     * @param string $sql
     * @param int $offset
     * @param int $limit
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return void
     */
    #[\Override]
    public function applyLimit(string &$sql, int $offset, int $limit, ?Criteria $criteria = null): void
    {
        if ($limit >= 0) {
            $sql .= ' LIMIT ' . $limit . ($offset > 0 ? ' OFFSET ' . $offset : '');
        } elseif ($offset > 0) {
            $sql .= sprintf(' LIMIT -1 OFFSET %s', $offset);
        }
    }

    /**
     * @param string|null $seed
     *
     * @return string
     */
    #[\Override]
    public function random(?string $seed = null): string
    {
        return 'random()';
    }

    /**
     * @see SqlAdapterInterface::applyLock()
     *
     * @param string $sql
     * @param \Propel\Runtime\ActiveQuery\Lock $lock
     *
     * @return void
     */
    #[\Override]
    public function applyLock(string &$sql, Lock $lock): void
    {
    }
}
