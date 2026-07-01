<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The `ON DUPLICATE KEY UPDATE` assignment refinement.
 *
 * @internal
 * @phpstan-require-extends InsertBuilder
 */
trait AddsUpsertAssignment
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
