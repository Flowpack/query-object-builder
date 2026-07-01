<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The lock wait-policy refinements (`NOWAIT` / `SKIP LOCKED`) shared by both
 * dialects' `ForSelectBuilder`. Reconstructing the {@see LockingClause} happens
 * here in {@see deriveLocking()} alone, which the MySQL-only `of()` also uses.
 *
 * @internal
 * @phpstan-require-extends AbstractSelectBuilder
 */
trait SetsLockWaitPolicy
{
    public function nowait(): static
    {
        return $this->deriveLocking(waitPolicy: 'NOWAIT');
    }

    public function skipLocked(): static
    {
        return $this->deriveLocking(waitPolicy: 'SKIP LOCKED');
    }

    /**
     * @param list<string>|null $ofTables
     */
    protected function deriveLocking(?array $ofTables = null, ?string $waitPolicy = null): static
    {
        $lc = $this->parts->lockingClause;
        assert($lc !== null);

        return $this->derive(static::class, lockingClause: new LockingClause(
            $lc->clause,
            $ofTables ?? $lc->ofTables,
            $waitPolicy ?? $lc->waitPolicy,
        ));
    }
}
