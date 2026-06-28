<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The `WINDOW` clause builder state right after an ORDER BY expression, where
 * {@see asc()} / {@see desc()} / {@see nullsFirst()} / {@see nullsLast()} act on
 * that last order by term of the current window.
 */
final class OrderByWindowSelectBuilder extends OrderBySelectBuilder
{
    use WindowDefining;

    public function asc(): self
    {
        return $this->rebuildLastWindowOrderBy(order: SortOrder::Asc);
    }

    public function desc(): self
    {
        return $this->rebuildLastWindowOrderBy(order: SortOrder::Desc);
    }

    public function nullsFirst(): self
    {
        return $this->rebuildLastWindowOrderBy(nulls: SortNulls::First);
    }

    public function nullsLast(): self
    {
        return $this->rebuildLastWindowOrderBy(nulls: SortNulls::Last);
    }

    private function rebuildLastWindowOrderBy(?SortOrder $order = null, ?SortNulls $nulls = null): self
    {
        $def = $this->lastWindowDefinition();

        $orderBys = $def->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order ?? $clause->order, $nulls ?? $clause->nulls);

        return $this->deriveWindow(self::class, new WindowDefinition($def->existingWindowName, $def->partitionBy, $orderBys));
    }
}
