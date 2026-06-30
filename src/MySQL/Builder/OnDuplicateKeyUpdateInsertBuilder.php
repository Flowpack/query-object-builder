<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The INSERT builder state inside an `ON DUPLICATE KEY UPDATE` clause, where
 * {@see set()} adds the assignments applied when a unique key already exists.
 */
final class OnDuplicateKeyUpdateInsertBuilder extends InsertBuilder
{
    /**
     * Add a `column = value` assignment. Reference the row that would have been
     * inserted via `Q::inserted('col')`.
     */
    public function set(string $columnName, Exp $value): self
    {
        return $this->derive(self::class, onDuplicateKeyUpdateSetItems: [
            ...$this->onDuplicateKeyUpdateSetItems,
            new UpdateSetItem($columnName, $value),
        ]);
    }
}
