<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A FROM-clause item: a relation (table, function, subquery or join) with an
 * optional alias and column aliases, optionally LATERAL or ONLY.
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
        public readonly bool $only = false,
        public readonly array $columnAliases = [],
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->lateral && $this->only) {
            $sb->addError(new QueryBuilderException('FROM item cannot be both LATERAL and ONLY'));

            return;
        }

        $s = '';
        if ($this->only) {
            $s .= 'ONLY ';
        }
        if ($this->lateral) {
            $s .= 'LATERAL ';
        }
        if ($s !== '') {
            $sb->writeString($s);
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
