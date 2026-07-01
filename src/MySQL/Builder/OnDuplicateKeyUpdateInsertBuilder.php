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
     * Add a `column = value` assignment. Reference the proposed row via
     * `Q::values('col')`, or via `Q::n('new.col')` after aliasing it with
     * {@see InsertValuesBuilder::as()}.
     */
    public function set(string $columnName, Exp $value): static
    {
        return $this->derive(static::class, onDuplicateKeyUpdateSetItems: [
            ...$this->onDuplicateKeyUpdateSetItems,
            new UpdateSetItem($columnName, $value),
        ]);
    }
}
