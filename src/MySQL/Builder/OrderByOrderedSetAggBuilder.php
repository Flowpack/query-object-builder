<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The ordered-set aggregate state right after its ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that term.
 */
final class OrderByOrderedSetAggBuilder extends OrderedSetAggBuilder
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
        $orderBys = $this->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order);

        return new self($this->name, $this->args, $this->requires, $orderBys, $this->withinGroupOrderBy);
    }
}
