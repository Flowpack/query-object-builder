<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A `CONVERT(expr, type)` expression — the function-call form of a type cast.
 *
 * @internal
 */
final class ConvertExp extends ExpBase
{
    public function __construct(
        private readonly Exp $expr,
        private readonly TypeExp $type,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('CONVERT(');
        $this->expr->writeSql($sb);
        $sb->writeString(', ');
        $this->type->writeSql($sb);
        $sb->writeString(')');
    }
}
