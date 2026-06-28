<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A conjunction (AND) or disjunction (OR) of expressions.
 *
 * A junction of a single expression writes just that expression. Nested
 * junctions are wrapped in parentheses.
 */
final class Junction implements Exp, Precedencer
{
    /**
     * @param list<Exp> $exps
     */
    private function __construct(
        private readonly array $exps,
        private readonly string $op,
    ) {
    }

    public static function and(Exp ...$exps): self
    {
        return new self(array_values($exps), 'AND');
    }

    public static function or(Exp ...$exps): self
    {
        return new self(array_values($exps), 'OR');
    }

    public function precedence(): int
    {
        return Precedence::of($this->op);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if (count($this->exps) === 1) {
            $this->exps[0]->writeSql($sb);

            return;
        }

        foreach ($this->exps as $i => $exp) {
            if ($i > 0) {
                $sb->writeString(' ' . $this->op . ' ');
            }
            // Wrap nested junctions in parentheses.
            if ($exp instanceof self) {
                $sb->writeString('(');
                $exp->writeSql($sb);
                $sb->writeString(')');
            } else {
                $exp->writeSql($sb);
            }
        }
    }
}
