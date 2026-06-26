<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding a plain item to the FROM clause.
 *
 * Here {@see as()} aliases the last added from item.
 */
final class FromSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the last added from item.
     */
    public function as(string $alias): self
    {
        $parts = clone $this->parts;
        $lastIdx = array_key_last($parts->from);
        assert($lastIdx !== null);

        $fromItem = clone $parts->from[$lastIdx];
        $fromItem->alias = $alias;
        $parts->from[$lastIdx] = $fromItem;

        return $this->into(self::class, $parts);
    }
}
