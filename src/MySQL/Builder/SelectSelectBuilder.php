<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding expressions to the select list, where
 * {@see as()} aliases the last added select expression and {@see distinct()}
 * marks the select as DISTINCT.
 */
final class SelectSelectBuilder extends SelectBuilder
{
    /**
     * Set the output alias for the last added select expression.
     */
    public function as(string $alias): self
    {
        $selectList = $this->parts->selectList;
        $lastIdx = array_key_last($selectList);
        assert($lastIdx !== null);
        $selectList[$lastIdx] = new OutputExpr($selectList[$lastIdx]->exp, $alias);

        return $this->derive(self::class, selectList: $selectList);
    }

    /**
     * Make the select `DISTINCT`.
     */
    public function distinct(): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, distinct: true);
    }
}
