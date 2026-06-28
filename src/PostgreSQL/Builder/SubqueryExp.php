<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A row/array comparison operand, `ANY (...)` or `ALL (...)`. A subquery
 * renders its own parentheses; any other expression is wrapped here.
 */
final class SubqueryExp implements Exp
{
    public function __construct(
        private readonly string $op,
        private readonly Exp $exp,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->op . ' ');

        $isSelect = $this->exp instanceof SelectBuilder;
        if (!$isSelect) {
            $sb->writeString('(');
        }
        $this->exp->writeSql($sb);
        if (!$isSelect) {
            $sb->writeString(')');
        }
    }
}
