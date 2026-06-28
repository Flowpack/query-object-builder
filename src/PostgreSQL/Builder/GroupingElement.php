<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single GROUP BY grouping element: a list of expressions. A single
 * expression is written as-is, multiple expressions are parenthesized.
 *
 * This is the simple form of the Go `builder.groupingElement` (ROLLUP / CUBE /
 * GROUPING SETS are not yet supported).
 */
final class GroupingElement
{
    /**
     * @param list<Exp> $exps
     */
    public function __construct(
        public array $exps,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if (count($this->exps) === 1) {
            $this->exps[0]->writeSql($sb);

            return;
        }

        $sb->writeString('(');
        foreach ($this->exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $exp->writeSql($sb);
        }
        $sb->writeString(')');
    }
}
