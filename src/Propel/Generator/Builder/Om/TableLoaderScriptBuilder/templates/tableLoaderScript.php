<?= '<?php'?>

$serviceContainer = \Propel\Runtime\Perpl::getServiceContainer();
$serviceContainer->initDatabaseMapFromDumps(<?= var_export($databaseNameToTableMapDumps, true) ?>);
