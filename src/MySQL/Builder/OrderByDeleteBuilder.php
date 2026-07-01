<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The DELETE builder state right after adding an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last term.
 */
final class OrderByDeleteBuilder extends DeleteBuilder
{
    public function asc(): static
    {
        return $this->derive(static::class, orderBys: $this->rebuildLastOrderBy(SortOrder::Asc));
    }

    public function desc(): static
    {
        return $this->derive(static::class, orderBys: $this->rebuildLastOrderBy(SortOrder::Desc));
    }

    /**
     * Return the order by list with the last term replaced by a copy carrying the
     * given sort direction.
     *
     * @return list<OrderByClause>
     */
    private function rebuildLastOrderBy(SortOrder $order): array
    {
        $orderBys = $this->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order);

        return $orderBys;
    }
}
