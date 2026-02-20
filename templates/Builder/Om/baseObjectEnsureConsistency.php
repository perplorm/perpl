<?php
/**
 * @var array{foreignObjectVar: string, columnVarName: string, getterId: string}[] $fkProperties
 */
?>

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database. It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @return void
     */
    public function ensureConsistency(): void
    {
<?php foreach($fkProperties as $p): ?>
        if ($this-><?= $p['foreignObjectVar'] ?> !== null && $this-><?= $p['columnVarName'] ?> !== $this-><?= $p['foreignObjectVar'] ?>->get<?= $p['getterId'] ?>()) {
            $this-><?= $p['foreignObjectVar'] ?> = null;
        }
<?php endforeach; ?>
    }
