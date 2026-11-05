<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\OutputGroup;

use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Model\Column;
use function array_filter;
use function preg_replace_callback;

class OgQueryModifier
{
    protected OutputGroupBehavior $behavior;

    /**
     * @param \Propel\Generator\Behavior\OutputGroup\OutputGroupBehavior $behavior
     */
    public function __construct(OutputGroupBehavior $behavior)
    {
        $this->behavior = $behavior;
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Builder\Om\QueryBuilder $queryBuilder
     *
     * @return void
     */
    public function queryFilter(string &$script, QueryBuilder $queryBuilder)
    {
        $modelType = $queryBuilder->getStubObjectBuilder()->getFullyQualifiedClassName();
        $script = $this->addMethodSignaturesToDocBlock($script, $modelType);
    }

    /**
     * @param string $script
     * @param string $modelType
     *
     * @return string
     */
    protected function addMethodSignaturesToDocBlock(string $script, string $modelType): string
    {
        $table = $this->behavior->getTable();
        $nonUniqueColumns = array_filter(
            $table->getColumns(),
            fn (Column $column) => !$table->isUnique([$column]),
        );

        $collectionClass = $this->behavior->getObjectCollectionClass();
        if ($collectionClass === '\\' . ObjectCollectionWithOutputGroups::class) {
            $collectionClass .= "<$modelType>";
        }

        $methodDeclarations = $this->behavior->renderLocalTemplate('queryToOutputGroup', [
            'collectionClass' => $collectionClass,
            'nonUniqueColumns' => $nonUniqueColumns,

        ]);

        $pattern = "/(\h\*\/\sabstract class)/"; // end of doc block "*/\nabstract class"

        return preg_replace_callback($pattern, fn (array $match) => $methodDeclarations . $match[0], $script, 1);
    }
}
