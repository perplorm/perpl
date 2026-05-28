
/**
 * Copy the data of the objects satisfying the query into <?php echo $archiveTablePhpName ?> archive objects.
 * The archived objects are then saved.
 * If any of the objects has already been archived, the archived object
 * is updated and not duplicated.
 * Warning: This termination methods issues 2n+1 queries.
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface|null $con    Connection to use.
 * @param bool $useLittleMemory Whether to use OnDemandFormatter to retrieve objects.
 *               Set to false if the identity map matters.
 *               Set to true (default) to use less memory.
 *
 * @return int the number of archived objects
 */
public function archive($con = null, $useLittleMemory = true)
{
    $criteria = clone $this;
    // prepare the query
    $criteria->setWith([]);
    if ($useLittleMemory) {
        $criteria->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
    }

    $con ??= Perpl::getServiceContainer()->getWriteConnection(<?php echo $modelTableMap ?>::DATABASE_NAME);

    return $con->transaction(function () use ($con, $criteria) {
        $totalArchivedObjects = 0;

        // archive all results one by one
        foreach ($criteria->find($con) as $object) {
            $object->archive($con);
            $totalArchivedObjects++;
        }

        return $totalArchivedObjects;
    });
}
