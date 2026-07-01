<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The window function builder state right after an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last order by term.
 */
final class OrderByWindowFuncCallBuilder extends WindowFuncCallBuilder
{
    public function asc(): self
    {
        return $this->rebuildLastOrderBy(SortOrder::Asc);
    }

    public function desc(): self
    {
        return $this->rebuildLastOrderBy(SortOrder::Desc);
    }

    private function rebuildLastOrderBy(SortOrder $order): self
    {
        $orderBys = $this->definition->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order);

        return new self($this->funcCall, new WindowDefinition(
            $this->definition->existingWindowName,
            $this->definition->partitionBy,
            $orderBys,
            $this->definition->frame,
        ));
    }
}
