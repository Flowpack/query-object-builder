<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single ORDER BY clause: an expression with optional sort order and nulls
 * ordering.
 *
 * Mutable by design: the immutable builders copy it (via clone) before setting
 * the order / nulls, see {@see OrderBySelectBuilder}.
 */
final class OrderByClause
{
    public function __construct(
        public Exp $exp,
        public ?SortOrder $order = null,
        public ?SortNulls $nulls = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->exp->writeSql($sb);

        $s = '';
        if ($this->order !== null) {
            $s .= ' ' . $this->order->value;
        }
        if ($this->nulls !== null) {
            $s .= ' ' . $this->nulls->value;
        }
        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
