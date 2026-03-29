
    /**
     * Method to invalidate the instance pool of all tables related to <?= $tableName ?> 
     * by a foreign key with ON DELETE CASCADE
     *
     * @return void
     */
    public static function clearRelatedInstancePool(): void
    {
<?php if ($relatedTableMapClassNames): ?>
        // Invalidate objects in related instance pools,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
<?php foreach ($relatedTableMapClassNames as $tableMapClassNames): ?>
        <?= $tableMapClassNames ?>::clearInstancePool();
<?php endforeach; endif; ?>
    }
