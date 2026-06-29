<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The DELETE builder state right after a RETURNING expression, where {@see as()}
 * sets the output name of that last expression.
 */
final class ReturningDeleteBuilder extends DeleteBuilder
{
    /**
     * Set the output name for the last RETURNING expression.
     */
    public function as(string $outputName): DeleteBuilder
    {
        $returningItems = $this->returningItems;
        $lastIdx = array_key_last($returningItems);
        assert($lastIdx !== null);

        $item = $returningItems[$lastIdx];
        $returningItems[$lastIdx] = new ReturningItem($item->outputExpression, $outputName);

        return $this->derive(DeleteBuilder::class, returningItems: $returningItems);
    }
}
