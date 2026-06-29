<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The INSERT builder state right after a RETURNING expression, where {@see as()}
 * sets the output name of that last expression.
 */
final class ReturningInsertBuilder extends InsertBuilder
{
    /**
     * Set the output name for the last RETURNING expression.
     */
    public function as(string $outputName): InsertBuilder
    {
        $returningItems = $this->returningItems;
        $lastIdx = array_key_last($returningItems);
        assert($lastIdx !== null);

        $item = $returningItems[$lastIdx];
        $returningItems[$lastIdx] = new ReturningItem($item->outputExpression, $outputName);

        return $this->derive(InsertBuilder::class, returningItems: $returningItems);
    }
}
