<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery;

use Countable;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Propel;
use function count;
use function is_array;
use function is_object;
use function is_scalar;
use function serialize;

trait InstancePoolTrait
{
    /**
     * @var array<object>
     */
    public static $instances = [];

    /**
     * @param object $object
     * @param string|null $key
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return void
     */
    public static function addInstanceToPool(object $object, ?string $key = null): void
    {
        throw new LogicException('Table without PK cannot use instance pool.');
    }

    /**
     * @deprecated Does not work reliably, use {@see static::getPrimaryKeyHashFromRow()} or {@see static::getPrimaryKeyHashFromObject()}
     *
     * @param mixed $value
     *
     * @return string|null
     */
    public static function getInstanceKey($value): ?string
    {
        if (!($value instanceof Criteria) && is_object($value)) {
            $pk = $value->getPrimaryKey();
            if (
                ((is_array($pk) || $pk instanceof Countable) && count($pk) > 1)
                || is_object($pk)
            ) {
                $pk = serialize($pk);
            }

            return (string)$pk;
        }

        if (is_scalar($value)) {
            // assume we've been passed a primary key
            return (string)$value;
        }

        return null;
    }

    /**
     * @param mixed $value
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return void
     */
    public static function removeInstanceFromPool($value): void
    {
        throw new LogicException('Table without PK cannot use instance pool.');
    }

    /**
     * @param string|null $key
     *
     * @return object|null
     */
    public static function getInstanceFromPool(?string $key): ?object
    {
        if ($key === null || !Propel::isInstancePoolingEnabled()) {
            return null;
        }

        return static::$instances[$key] ?? null;
    }

    /**
     * @return void
     */
    public static function clearInstancePool(): void
    {
        self::$instances = [];
    }

    /**
     * @return void
     */
    public static function clearRelatedInstancePool(): void
    {
    }
}
