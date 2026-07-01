<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The `ON DUPLICATE KEY UPDATE` assignment refinement, shared by both dialects'
 * `OnDuplicateKeyUpdateInsertBuilder`.
 *
 * @internal
 * @phpstan-require-extends AbstractInsertBuilder
 */
trait AddsUpsertAssignment
{
    /**
     * Add a `column = value` assignment. Reference the row that would have been
     * inserted via `Q::inserted('col')`.
     */
    public function set(string $columnName, Exp $value): static
    {
        return $this->derive(static::class, onDuplicateKeyUpdateSetItems: [
            ...$this->onDuplicateKeyUpdateSetItems,
            new UpdateSetItem($columnName, $value),
        ]);
    }
}
