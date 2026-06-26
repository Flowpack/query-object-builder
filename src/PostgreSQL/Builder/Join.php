<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A join within a FROM clause.
 *
 * Mutable by design: the immutable builders copy it (via clone) before setting
 * the alias / ON condition / USING columns, see {@see JoinSelectBuilder}.
 */
final class Join implements FromExp
{
    /**
     * @param list<string> $using
     */
    public function __construct(
        public JoinType $joinType,
        public bool     $lateral,
        public FromExp  $from,
        public string   $alias = '',
        public ?Exp     $on = null,
        public array    $using = [],
    )
    {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = $this->joinType->value;
        if ($this->lateral) {
            $s .= ' LATERAL';
        }
        $s .= ' ';
        $sb->writeString($s);
        $this->from->writeSql($sb);

        if ($this->alias !== '') {
            $sb->writeString(' AS ');
            $sb->writeString($this->alias);
        }

        if ($this->on !== null) {
            $sb->writeString(' ON ');
            $this->on->writeSql($sb);
        } elseif ($this->using !== []) {
            $sb->writeString(' USING (');
            foreach ($this->using as $i => $col) {
                if ($i > 0) {
                    $sb->writeString(', ');
                }
                $sb->writeString($col);
            }
            $sb->writeString(')');
        }
    }
}
