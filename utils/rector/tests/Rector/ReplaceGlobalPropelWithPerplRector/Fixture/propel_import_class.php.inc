<?php

namespace Rector\Tests\TypeDeclaration\Rector\ReplaceGlobalPropelWithPerplRector\Fixture;

use Propel\Runtime\Propel;

class Foo {
    const C = Propel::someMethod();

    public function __construct()
    {
        Propel::anotherMethod();
        $var = Propel::someOtherMethod();
        \Propel\Runtime\Propel::fullyQualifiedAccess();
    }
}
?>
-----
<?php

namespace Rector\Tests\TypeDeclaration\Rector\ReplaceGlobalPropelWithPerplRector\Fixture;

use Propel\Runtime\Perpl;

class Foo {
    const C = Perpl::someMethod();

    public function __construct()
    {
        Perpl::anotherMethod();
        $var = Perpl::someOtherMethod();
        \Propel\Runtime\Perpl::fullyQualifiedAccess();
    }
}
?>
