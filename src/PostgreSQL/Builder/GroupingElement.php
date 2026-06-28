<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A GROUP BY grouping element. A single expression is written as-is; multiple
 * expressions are parenthesized as a row.
 *
 * @internal
 */
final class GroupingElement
{
    /**
     * @param list<Exp> $exps
     */
    public function __construct(
        public readonly array $exps,
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
