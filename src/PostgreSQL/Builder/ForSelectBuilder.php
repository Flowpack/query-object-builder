<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after a locking clause was started (e.g.
 * {@see SelectBuilder::forUpdate()}), where {@see of()} names the locked tables
 * and {@see nowait()} / {@see skipLocked()} set the wait policy.
 */
final class ForSelectBuilder extends SelectBuilder
{
    /**
     * Restrict the lock to the given tables (`OF table [, ...]`).
     */
    public function of(string ...$tables): self
    {
        return $this->deriveLocking(ofTables: array_values($tables));
    }

    public function nowait(): self
    {
        return $this->deriveLocking(waitPolicy: 'NOWAIT');
    }

    public function skipLocked(): self
    {
        return $this->deriveLocking(waitPolicy: 'SKIP LOCKED');
    }

    /**
     * @param list<string>|null $ofTables
     */
    private function deriveLocking(?array $ofTables = null, ?string $waitPolicy = null): self
    {
        $lc = $this->parts->lockingClause;
        assert($lc !== null);

        return $this->derive(self::class, lockingClause: new LockingClause(
            $lc->lockStrength,
            $ofTables ?? $lc->ofTables,
            $waitPolicy ?? $lc->waitPolicy,
        ));
    }
}
