<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A row-locking clause, e.g. `FOR UPDATE`, `FOR SHARE OF a, b SKIP LOCKED`.
 *
 * @internal
 */
final class LockingClause
{
    /**
     * @param list<string> $ofTables
     */
    public function __construct(
        public readonly string $lockStrength,
        public readonly array $ofTables = [],
        public readonly string $waitPolicy = '',
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $s = 'FOR ' . $this->lockStrength;
        if ($this->ofTables !== []) {
            $s .= ' OF ' . implode(',', $this->ofTables);
        }
        if ($this->waitPolicy !== '') {
            $s .= ' ' . $this->waitPolicy;
        }
        $sb->writeString($s);
    }
}
