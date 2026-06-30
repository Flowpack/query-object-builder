<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A `CAST(expr AS type)` expression; the type vocabulary is the restricted CAST
 * target set (see {@see TypeExp}).
 */
final class CastExp extends ExpBase
{
    public function __construct(
        private readonly Exp $exp,
        private readonly TypeExp $type,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('CAST(');
        $this->exp->writeSql($sb);
        $sb->writeString(' AS ');
        $this->type->writeSql($sb);
        $sb->writeString(')');
    }
}
