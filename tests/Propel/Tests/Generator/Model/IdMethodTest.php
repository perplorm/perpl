<?php

namespace Propel\Tests\Generator\Model;

use LogicException;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\PgsqlPlatform;


class IdMethodTest extends ModelTestCase
{
    public function testKnowsWhenToGetValue()
    {
        foreach (IdMethod::cases() as $idMethod) {
            if ($idMethod === IdMethod::NO_ID_METHOD) {
                continue;
            }
            $loadBeforeOrAfter = $idMethod->isGetIdBeforeInsert() || $idMethod->isGetIdAfterInsert();
            $this->assertTrue($loadBeforeOrAfter, "IdMethod::{$idMethod->name} should know when to load id.");
        }
    }

    public function testNativeUsesSequenceRequiresPlatform()
    {
        $this->assertTrue(IdMethod::NATIVE->usesSequence(new PgsqlPlatform()));
        $this->assertFalse(IdMethod::NATIVE->usesSequence(new MysqlPlatform()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Cannot evaluate meta-value `NATIVE`");

        IdMethod::NATIVE->usesSequence();
    }
}
