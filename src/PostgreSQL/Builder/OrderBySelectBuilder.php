<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding an ORDER BY expression.
 *
 * Here {@see asc()} / {@see desc()} and {@see nullsFirst()} / {@see nullsLast()}
 * act on the last added order by clause.
 */
final class OrderBySelectBuilder extends SelectBuilder
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
        $parts = clone $this->parts;
        $lastIdx = array_key_last($parts->orderBys);
        assert($lastIdx !== null);

        $clause = clone $parts->orderBys[$lastIdx];
        $clause->order = $order;
        $parts->orderBys[$lastIdx] = $clause;

        return $this->into(self::class, $parts);
    }

    private function setNulls(SortNulls $nulls): self
    {
        $parts = clone $this->parts;
        $lastIdx = array_key_last($parts->orderBys);
        assert($lastIdx !== null);

        $clause = clone $parts->orderBys[$lastIdx];
        $clause->nulls = $nulls;
        $parts->orderBys[$lastIdx] = $clause;

        return $this->into(self::class, $parts);
    }
}
