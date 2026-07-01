<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A row-locking clause, e.g. `FOR UPDATE`, `FOR SHARE OF a, b SKIP LOCKED`, or
 * `LOCK IN SHARE MODE`. The lead keyword is dialect-chosen and stored verbatim.
 *
 * @internal
 */
final class LockingClause
{
    /**
     * @param list<string> $ofTables
     * @param Requirement|null $requires the dialect this lock spelling is available on, if it is dialect-specific
     */
    public function __construct(
        public readonly string $clause,
        public readonly array $ofTables = [],
        public readonly string $waitPolicy = '',
        public readonly ?Requirement $requires = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        if ($this->requires !== null) {
            $sb->requireAnyDialect($this->clause, $this->requires);
        }

        $s = $this->clause;
        if ($this->ofTables !== []) {
            // The OF table list is a MySQL extension, even on FOR UPDATE.
            $sb->requireDialect(Dialect::Mysql, 'the locking OF clause');
            $s .= ' OF ' . implode(',', $this->ofTables);
        }
        if ($this->waitPolicy !== '') {
            $s .= ' ' . $this->waitPolicy;
        }
        $sb->writeString($s);
    }
}
