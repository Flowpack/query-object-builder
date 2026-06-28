<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding a plain item to the FROM clause, where
 * {@see as()} aliases the last added from item.
 */
final class FromSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the last added from item.
     */
    public function as(string $alias): self
    {
        $from = $this->parts->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);
        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $alias, $item->lateral, $item->only, $item->columnAliases);

        return $this->derive(self::class, from: $from);
    }
}
