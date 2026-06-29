<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The INSERT builder state right after an ON CONFLICT clause, where the conflict
 * target can be refined with {@see onConstraint()} / {@see where()} and the
 * conflict action chosen with {@see doUpdate()} / {@see doNothing()}.
 */
final class OnConflictInsertBuilder extends InsertBuilder
{
    /**
     * Target a named constraint instead of conflict target expressions.
     */
    public function onConstraint(string $constraintName): self
    {
        return $this->derive(self::class, conflictConstraintName: $constraintName);
    }

    /**
     * Add a WHERE index predicate to the conflict target.
     * Multiple calls are joined with AND.
     */
    public function where(Exp $cond): self
    {
        return $this->derive(self::class, conflictTargetWhereConjunction: [...$this->conflictTargetWhereConjunction, $cond]);
    }

    /**
     * Use `DO UPDATE` as the conflict action; add assignments via
     * {@see OnConflictDoUpdateInsertBuilder::set()}.
     */
    public function doUpdate(): OnConflictDoUpdateInsertBuilder
    {
        return $this->derive(OnConflictDoUpdateInsertBuilder::class, conflictAction: 'DO UPDATE');
    }

    /**
     * Use `DO NOTHING` as the conflict action.
     */
    public function doNothing(): InsertBuilder
    {
        return $this->derive(InsertBuilder::class, conflictAction: 'DO NOTHING');
    }
}
