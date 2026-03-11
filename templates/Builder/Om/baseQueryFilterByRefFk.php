<?php
/**
 * @var string $varName
 * @var string $relationName
 * @var string $targetClassName
 * @var string $targetClassNameFq
 * @var bool $isComposite
 * @var array<array{getterId:string, columnName: string}|array{getterId: string, columnExpression: string, pdoBindingType: int}> $relationColumnValues
 */
?>

    /**
     * Filter the query by a related <?= $relationName ?> object
     *
     * @param <?= $targetClassNameFq ?>|\Propel\Runtime\Collection\ObjectCollection<<?= $targetClassNameFq ?>> <?= $varName ?> the related object to use as filter
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this
     */
    public function filterBy<?= $relationName ?>(<?= $targetClassName ?>|ObjectCollection <?= $varName ?>, ?string $comparison = null)
    {
        if (<?= $varName ?> instanceof <?= $targetClassName ?>) {
<?php
    foreach ($relationColumnValues as $v):
        if (count($v) === 2): 
?>
                $this->addUsingOperator($this->resolveLocalColumnByName('<?= $v['columnName'] ?>'), <?= $varName ?>->get<?= $v['getterId'] ?>(), $comparison);
<?php   else: ?>
                $this->where("<?= $v['columnExpression'] ?> = ?", <?= $varName ?>->get<?= $v['getterId'] ?>(), <?= $v['pdoBindingType'] ?>);
<?php   endif;
    endforeach;
?>
<?php if (!$isComposite): ?>
        } elseif (<?= $varName ?> instanceof ObjectCollection) {
            $this
                ->use<?= $relationName ?>Query()
                ->filterByPrimaryKeys(<?= $varName ?>->getPrimaryKeys())
                ->endUse();
<?php endif; ?>
        }

        return $this;
    }
