<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An array subscript expression: `base[index]` or a slice `base[lower:upper]`.
 *
 * The base is parenthesized unless it is a column reference, a positional
 * parameter or another subscript (where PostgreSQL allows omitting them).
 */
final class SubscriptExp extends ExpBase implements Precedencer
{
    public function __construct(
        private readonly Exp $base,
        private readonly Exp $subscript,
        private readonly ?Exp $upperBound = null,
    ) {
    }

    public function precedence(): int
    {
        return 5; // array element selection
    }

    public function writeSql(SqlBuilder $sb): void
    {
        // Parentheses may be omitted only for column references, positional
        // parameters and chained subscripts.
        $needsParens = !($this->base instanceof IdentExp
            || $this->base instanceof Arg
            || $this->base instanceof self);

        if ($needsParens) {
            $sb->writeString('(');
        }
        $this->base->writeSql($sb);
        if ($needsParens) {
            $sb->writeString(')');
        }

        $sb->writeString('[');
        $this->subscript->writeSql($sb);
        if ($this->upperBound !== null) {
            $sb->writeString(':');
            $this->upperBound->writeSql($sb);
        }
        $sb->writeString(']');
    }
}
