<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The aggregate builder state right after an ORDER BY expression was added, where
 * {@see asc()} / {@see desc()} / {@see nullsFirst()} / {@see nullsLast()} act on
 * that last order by clause.
 */
final class OrderByAggBuilder extends AggBuilder
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
        $orderBys = $this->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order ?? $clause->order, $nulls ?? $clause->nulls);

        return new self($this->name, $this->exps, $this->distinct, $orderBys, $this->filterConjunction, $this->withinGroupOrderBy);
    }
}
