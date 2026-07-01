<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A `value MEMBER OF (json_array)` test: whether the value is an element of the
 * given JSON array. The right-hand side is always parenthesized.
 */
final class MemberOfExp implements Exp
{
    public function __construct(
        private readonly Exp $value,
        private readonly Exp $jsonArray,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->value->writeSql($sb);
        $sb->writeString(' MEMBER OF (');
        $this->jsonArray->writeSql($sb);
        $sb->writeString(')');
    }
}
