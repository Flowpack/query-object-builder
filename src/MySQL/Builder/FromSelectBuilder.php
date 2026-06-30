<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding a plain item to the FROM clause, where
 * {@see as()} aliases the last added from item and {@see columnAliases()} names
 * its output columns.
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
        $from[$lastIdx] = new FromItem($item->from, $alias, $item->lateral, $item->columnAliases);

        return $this->derive(self::class, from: $from);
    }

    /**
     * Set the column aliases for the last added from item.
     */
    public function columnAliases(string ...$aliases): self
    {
        $from = $this->parts->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);
        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $item->alias, $item->lateral, array_values($aliases));

        return $this->derive(self::class, from: $from);
    }
}
