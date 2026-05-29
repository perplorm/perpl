<?php

declare(strict_types=1);

namespace Utils\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\UseItem;
use Rector\Rector\AbstractRector;

/**
 * @see \Rector\Tests\TypeDeclaration\Rector\ReplaceGlobalPropelWithPerplRector\ReplaceGlobalPropelWithPerplRectorTest
 */
final class ReplaceGlobalPropelWithPerplRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [
            UseItem::class,
            Name::class,
        ];
    }

    /**
     * @param \PhpParser\Node\Stmt\Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceOf UseItem && $node->name?->name === 'Propel\Runtime\Propel') {
            $node->name->name = 'Propel\Runtime\Perpl';
        }
        
        if (!$node instanceOf FullyQualified || $node->name !== 'Propel\Runtime\Propel') {
            return $node;
        }

        /** @var \PhpParser\Node\Name $originalName */
        $originalName = $node->getAttribute('originalName');
        
        if ($originalName->name === 'Propel') {
            return new Name('Perpl');
        }
        $node->name = 'Propel\Runtime\Perpl';

        return $node;
    }
}
