
    /**
     * Dummy Methods for interface - table has not PK, this will throw an exception.
     *
<?php foreach ($paramDocs as $paramDoc): ?>
     * @param <?= $paramDoc ?>
<?php endforeach; ?>
     *
     * @throws \LogicException";
     *
     * @return <?= $returnTypeDoc ?>
     */
    public function <?= $functionDeclaration ?>
    {
        throw new LogicException('The <?= $objectName ?> object has no primary key');
    }
