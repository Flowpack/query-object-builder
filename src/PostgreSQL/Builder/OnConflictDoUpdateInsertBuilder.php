<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The INSERT builder state inside an `ON CONFLICT ... DO UPDATE` action, where
 * {@see set()} adds `SET column = value` assignments and {@see where()} adds a
 * condition on the update.
 */
final class OnConflictDoUpdateInsertBuilder extends InsertBuilder
{
    /**
     * Add a `SET column = value` assignment to the DO UPDATE action.
     */
    public function set(string $columnName, Exp $value): self
    {
        return $this->derive(self::class, conflictDoUpdateSetItems: [...$this->conflictDoUpdateSetItems, new UpdateSetItem($columnName, $value)]);
    }

    /**
     * Add a WHERE condition to the DO UPDATE action.
     * Multiple calls are joined with AND.
     */
    public function where(Exp $cond): self
    {
        return $this->derive(self::class, conflictDoUpdateWhereConjunction: [...$this->conflictDoUpdateWhereConjunction, $cond]);
    }
}
