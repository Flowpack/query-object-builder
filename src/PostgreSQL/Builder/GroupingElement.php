<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A GROUP BY grouping element: one or more expression sets, optionally wrapped
 * in `ROLLUP` / `CUBE` / `GROUPING SETS`.
 *
 * A set of a single expression is written as-is; a set of zero or several
 * expressions is parenthesized as a row (so an empty set renders as `()`).
 *
 * @internal
 */
final class GroupingElement
{
    /**
     * @param list<list<Exp>> $sets
     */
    public function __construct(
        public readonly array $sets,
        public readonly ?GroupingType $groupingType = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->groupingType === null) {
            $this->writeSet($sb, $this->sets[0]);

            return;
        }

        // A single set follows the keyword directly; several are wrapped in parens.
        $multiple = count($this->sets) > 1;
        $sb->writeString($this->groupingType->value . ($multiple ? ' (' : ' '));
        foreach ($this->sets as $i => $set) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $this->writeSet($sb, $set);
        }
        if ($multiple) {
            $sb->writeString(')');
        }
    }

    /**
     * @param list<Exp> $exps
     */
    private function writeSet(SqlBuilder $sb, array $exps): void
    {
        if (count($exps) === 1) {
            $exps[0]->writeSql($sb);

            return;
        }

        $sb->writeString('(');
        foreach ($exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $exp->writeSql($sb);
        }
        $sb->writeString(')');
    }
}
