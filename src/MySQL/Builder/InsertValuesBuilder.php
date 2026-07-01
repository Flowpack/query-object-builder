<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The INSERT builder state right after the value rows were given, where {@see as()}
 * aliases the proposed row so it can be referenced by name in the upsert.
 */
final class InsertValuesBuilder extends InsertBuilder
{
    /**
     * Alias the proposed row (rendered as `AS alias` after the value rows), so its
     * values can be referenced as `alias.col` (via `Q::n('alias.col')`) inside
     * `ON DUPLICATE KEY UPDATE`.
     */
    public function as(string $rowAlias): static
    {
        return $this->derive(static::class, rowAlias: $rowAlias);
    }
}
