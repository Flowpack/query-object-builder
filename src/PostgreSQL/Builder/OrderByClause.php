<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single ORDER BY term: an expression with optional sort direction and nulls
 * placement.
 *
 * @internal
 */
final class OrderByClause
{
    public function __construct(
        public readonly Exp $exp,
        public readonly ?SortOrder $order = null,
        public readonly ?SortNulls $nulls = null,
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
