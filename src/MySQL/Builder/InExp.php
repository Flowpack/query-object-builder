<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * An `IN` / `NOT IN` expression. The right-hand side renders its own
 * parentheses (a subquery or an expression list).
 */
final class InExp implements Exp
{
    public function __construct(
        private readonly Exp $lft,
        private readonly string $op,
        private readonly SelectOrExpressions $rgt,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->lft->writeSql($sb);
        $sb->writeString(' ' . $this->op . ' ');
        $this->rgt->writeSql($sb);
    }
}
