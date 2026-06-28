<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding an ORDER BY expression, where
 * {@see asc()} / {@see desc()} and {@see nullsFirst()} / {@see nullsLast()} act
 * on that last order by term.
 *
 * {@see OrderByWindowSelectBuilder} specializes this for the WINDOW clause, so
 * that a window's `orderBy()` stays assignable where an ORDER BY builder is expected.
 */
class OrderBySelectBuilder extends SelectBuilder
{
    public function asc(): self
    {
        return $this->derive(self::class, orderBys: $this->rebuildLastOrderBy(order: SortOrder::Asc));
    }

    public function desc(): self
    {
        return $this->derive(self::class, orderBys: $this->rebuildLastOrderBy(order: SortOrder::Desc));
    }

    public function nullsFirst(): self
    {
        return $this->derive(self::class, orderBys: $this->rebuildLastOrderBy(nulls: SortNulls::First));
    }

    public function nullsLast(): self
    {
        return $this->derive(self::class, orderBys: $this->rebuildLastOrderBy(nulls: SortNulls::Last));
    }

    /**
     * Return the order by list with the last term replaced by a copy carrying the
     * given overrides.
     *
     * @return list<OrderByClause>
     */
    private function rebuildLastOrderBy(?SortOrder $order = null, ?SortNulls $nulls = null): array
    {
        $orderBys = $this->parts->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order ?? $clause->order, $nulls ?? $clause->nulls);

        return $orderBys;
    }
}
