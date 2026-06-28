<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after setting/modifying the JSON selection (via
 * {@see SelectBuilder::applySelectJson()}).
 *
 * Here {@see as()} aliases the JSON selection, which is always the first element
 * of the select list.
 */
final class SelectJsonSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the JSON selection.
     */
    public function as(string $alias): self
    {
        $parts = clone $this->parts;
        $parts->selectJsonAlias = $alias;

        return $this->into(self::class, $parts);
    }
}
