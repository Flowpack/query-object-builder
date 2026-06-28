<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A unary expression with a prefix (e.g. `NOT x`) and/or a suffix (e.g.
 * `x IS NULL`). The operand is parenthesized when it binds less tightly.
 */
final class UnaryExp implements Exp, Precedencer
{
    public function __construct(
        private readonly Exp $exp,
        private readonly int $precedence,
        private readonly string $prefix = '',
        private readonly string $suffix = '',
    ) {
    }

    public function precedence(): int
    {
        return $this->precedence;
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $needsParens = $this->exp instanceof Precedencer && $this->exp->precedence() < $this->precedence;

        $s = $this->prefix !== '' ? $this->prefix . ' ' : '';
        if ($needsParens) {
            $s .= '(';
        }
        $sb->writeString($s);

        $this->exp->writeSql($sb);

        $s = $needsParens ? ')' : '';
        if ($this->suffix !== '') {
            $s .= ' ' . $this->suffix;
        }
        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
