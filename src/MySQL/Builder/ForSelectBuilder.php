<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

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
    public function of(string ...$tables): static
    {
        return $this->deriveLocking(ofTables: array_values($tables));
    }

    public function nowait(): static
    {
        return $this->deriveLocking(waitPolicy: 'NOWAIT');
    }

    public function skipLocked(): static
    {
        return $this->deriveLocking(waitPolicy: 'SKIP LOCKED');
    }

    /**
     * Reconstruct the locking clause with the given OF tables / wait policy applied
     * (this is the one place the {@see LockingClause} is rebuilt).
     *
     * @param list<string>|null $ofTables
     */
    private function deriveLocking(?array $ofTables = null, ?string $waitPolicy = null): static
    {
        $lc = $this->parts->lockingClause;
        assert($lc !== null);

        return $this->derive(static::class, lockingClause: new LockingClause(
            $lc->clause,
            $ofTables ?? $lc->ofTables,
            $waitPolicy ?? $lc->waitPolicy,
            $lc->requires,
        ));
    }
}
