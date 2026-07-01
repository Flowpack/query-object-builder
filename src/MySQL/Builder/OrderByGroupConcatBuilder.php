<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The GROUP_CONCAT builder state right after an ORDER BY expression, where
 * {@see asc()} / {@see desc()} set the sort direction of that last term.
 */
final class OrderByGroupConcatBuilder extends GroupConcatBuilder
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

        return new self($this->exps, $this->distinct, $orderBys, $this->separator);
    }
}
