<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single item in the FROM clause: a table/function/subquery/join with an
 * optional alias and column aliases, optionally marked LATERAL or ONLY.
 *
 * Mutable by design: the immutable builders copy it (via clone) before changing
 * the alias, see {@see FromSelectBuilder::as()}.
 */
final class FromItem
{
    /**
     * @param list<string> $columnAliases
     */
    public function __construct(
        public FromExp $from,
        public string $alias = '',
        public bool $lateral = false,
        public bool $only = false,
        public array $columnAliases = [],
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->lateral && $this->only) {
            $sb->addError(new QueryBuilderException('from item: cannot specify both LATERAL and ONLY'));

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
