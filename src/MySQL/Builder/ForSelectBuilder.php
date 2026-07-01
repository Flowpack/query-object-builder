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
    use SetsLockWaitPolicy;

    /**
     * Restrict the lock to the given tables (`OF table [, ...]`).
     */
    public function of(string ...$tables): static
    {
        return $this->deriveLocking(ofTables: array_values($tables));
    }
}
