<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A parenthesized list of expressions, e.g. `(1, 2, 3)`.
 *
 * Used as the right-hand side of `IN` (via {@see Q::exps()} / {@see Q::args()}).
 */
final class Expressions extends ExpBase implements SelectOrExpressions
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
