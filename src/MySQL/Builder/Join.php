<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A join within a FROM clause.
 *
 * @internal
 */
final class Join implements FromExp
{
    /**
     * @param list<string> $using
     */
    public function __construct(
        public readonly JoinType $joinType,
        public readonly bool $lateral,
        public readonly FromExp $from,
        public readonly string $alias = '',
        public readonly ?Exp $on = null,
        public readonly array $using = [],
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = $this->joinType->value;
        if ($this->lateral) {
            $sb->requireDialect(Dialect::Mysql, 'LATERAL');
            $s .= ' LATERAL';
        }
        $s .= ' ';
        $sb->writeString($s);
        $this->from->writeSql($sb);

        if ($this->alias !== '') {
            $sb->writeString(' AS ' . $this->alias);
        }

        if ($this->on !== null) {
            $sb->writeString(' ON ');
            $this->on->writeSql($sb);
        } elseif ($this->using !== []) {
            $sb->writeString(' USING (' . implode(', ', $this->using) . ')');
        }
    }
}
