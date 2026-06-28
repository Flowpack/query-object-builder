<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A plain function-call expression, e.g. `COALESCE(a, b)`.
 *
 * Port of the Go `builder.funcExp` / `builder.FuncExp` (the simple expression
 * form, as opposed to the richer FuncBuilder used in FROM clauses).
 */
final class FuncExp extends ExpBase
{
    /**
     * @param list<Exp> $args
     */
    public function __construct(
        private readonly string $name,
        private readonly array $args,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->name . '(');
        foreach ($this->args as $i => $arg) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $arg->writeSql($sb);
        }
        $sb->writeString(')');
    }
}
