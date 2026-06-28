<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after setting or modifying the JSON selection (via
 * {@see SelectBuilder::applySelectJson()}), where {@see as()} aliases it. The
 * JSON selection is always the first element of the select list.
 */
final class SelectJsonSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the JSON selection.
     */
    public function as(string $alias): self
    {
        return $this->derive(self::class, selectJsonAlias: $alias);
    }
}
