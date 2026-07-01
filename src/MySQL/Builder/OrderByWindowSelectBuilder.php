<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The `WINDOW` clause builder state right after an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last order by term
 * of the current window.
 */
final class OrderByWindowSelectBuilder extends OrderBySelectBuilder
{
    use WindowDefining;

    public function asc(): static
    {
        return $this->rebuildLastWindowOrderBy(SortOrder::Asc);
    }

    public function desc(): static
    {
        return $this->rebuildLastWindowOrderBy(SortOrder::Desc);
    }

    private function rebuildLastWindowOrderBy(SortOrder $order): self
    {
        $def = $this->lastWindowDefinition();

        $orderBys = $def->orderBys;
        $lastIdx = array_key_last($orderBys);
        assert($lastIdx !== null);

        $clause = $orderBys[$lastIdx];
        $orderBys[$lastIdx] = new OrderByClause($clause->exp, $order);

        return $this->deriveWindow(self::class, new WindowDefinition($def->existingWindowName, $def->partitionBy, $orderBys, $def->frame));
    }
}
