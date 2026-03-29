<?php
    $pkSize = count($columnPhpNames);
?>

    /**
     * Filter the query by primary key
     *
     * @param <?= $pkType ?> $key
     *
     * @return static
     */
    public function filterByPrimaryKey($key)
    {
<?php if ($pkSize === 1): ?>
        return $this->filterBy<?= $columnPhpNames[0] ?>($key);
<?php else:?>
        return $this  
<?php foreach ($columnPhpNames as $index => $columnPhpName):?>
                ->filterBy<?= $columnPhpName ?>($key[<?= $index ?>])<?= $index === $pkSize-1 ? ';' : "\n" ?>
<?php endforeach; ?>           
<?php endif; ?>
    }
