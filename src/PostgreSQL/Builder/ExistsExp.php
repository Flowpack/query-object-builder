<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An `EXISTS (subquery)` expression.
 */
final class ExistsExp implements Exp
{
    public function __construct(
        private readonly SelectBuilder $subquery,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        // The subquery renders its own surrounding parentheses.
        $sb->writeString('EXISTS ');
        $this->subquery->writeSql($sb);
    }
}
