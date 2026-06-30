<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single ORDER BY term: an expression with an optional sort direction.
 * MySQL/MariaDB have no `NULLS FIRST/LAST`.
 *
 * @internal
 */
final class OrderByClause
{
    public function __construct(
        public readonly Exp $exp,
        public readonly ?SortOrder $order = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->exp->writeSql($sb);

        if ($this->order !== null) {
            $sb->writeString(' ' . $this->order->value);
        }
    }
}
