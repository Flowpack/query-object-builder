<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The window function builder state right after an ORDER BY expression, where
 * {@see asc()} / {@see desc()} / {@see nullsFirst()} / {@see nullsLast()} act on
 * that last order by term.
 */
final class OrderByWindowFuncCallBuilder extends WindowFuncCallBuilder
{
    public function asc(): self
    {
        return $this->rebuildLastOrderBy(order: SortOrder::Asc);
    }

    public function desc(): self
    {
        return $this->rebuildLastOrderBy(order: SortOrder::Desc);
    }

    public function nullsFirst(): self
    {
        return $this->rebuildLastOrderBy(nulls: SortNulls::First);
    }

    public function nullsLast(): self
    {
        return $this->rebuildLastOrderBy(nulls: SortNulls::Last);
    }

    private function rebuildLastOrderBy(?SortOrder $order = null, ?SortNulls $nulls = null): self
    {
        $orderBys = $this->definition->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order ?? $clause->order, $nulls ?? $clause->nulls);

        return new self($this->funcCall, new WindowDefinition(
            $this->definition->existingWindowName,
            $this->definition->partitionBy,
            $orderBys,
        ));
    }
}
