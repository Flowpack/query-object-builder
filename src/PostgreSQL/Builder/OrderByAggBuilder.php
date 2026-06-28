<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The aggregate builder state right after an ORDER BY expression was added;
 * {@see asc()} / {@see desc()} / {@see nullsFirst()} / {@see nullsLast()} act on
 * the last added order by clause.
 */
final class OrderByAggBuilder extends AggBuilder
{
    public function asc(): self
    {
        return $this->setOrder(SortOrder::Asc);
    }

    public function desc(): self
    {
        return $this->setOrder(SortOrder::Desc);
    }

    public function nullsFirst(): self
    {
        return $this->setNulls(SortNulls::First);
    }

    public function nullsLast(): self
    {
        return $this->setNulls(SortNulls::Last);
    }

    private function setOrder(SortOrder $order): self
    {
        $b = clone $this;
        $b->orderBys = $this->orderBys;
        $lastIdx = array_key_last($b->orderBys);
        assert($lastIdx !== null);

        $clause = clone $b->orderBys[$lastIdx];
        $clause->order = $order;
        $b->orderBys[$lastIdx] = $clause;

        return $b;
    }

    private function setNulls(SortNulls $nulls): self
    {
        $b = clone $this;
        $b->orderBys = $this->orderBys;
        $lastIdx = array_key_last($b->orderBys);
        assert($lastIdx !== null);

        $clause = clone $b->orderBys[$lastIdx];
        $clause->nulls = $nulls;
        $b->orderBys[$lastIdx] = $clause;

        return $b;
    }
}
