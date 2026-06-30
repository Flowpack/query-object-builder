<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A FROM-clause item: a relation (table, subquery or join) with an optional
 * alias and column aliases, optionally LATERAL.
 *
 * @internal
 */
final class FromItem
{
    /**
     * @param list<string> $columnAliases
     */
    public function __construct(
        public readonly FromExp $from,
        public readonly string $alias = '',
        public readonly bool $lateral = false,
        public readonly array $columnAliases = [],
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->lateral) {
            $sb->writeString('LATERAL ');
        }

        $this->from->writeSql($sb);

        $s = '';
        if ($this->alias !== '') {
            $s .= ' AS ' . $this->alias;
        }
        if ($this->columnAliases !== []) {
            if ($this->alias === '') {
                $s .= ' AS';
            }
            $s .= ' (' . implode(',', $this->columnAliases) . ')';
        }
        if ($s !== '') {
            $sb->writeString($s);
        }
    }
}
