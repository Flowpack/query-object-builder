<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Refinements for the last plain FROM item: an alias and derived-table column
 * aliases. Shared by both dialects' `FromSelectBuilder`.
 *
 * @internal
 * @phpstan-require-extends SelectBuilder
 */
trait AliasesLastFromItem
{
    /**
     * Set the alias for the last added from item.
     */
    public function as(string $alias): static
    {
        $from = $this->parts->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);
        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $alias, $item->lateral, $item->columnAliases);

        return $this->derive(static::class, from: $from);
    }

    /**
     * Set the column aliases for the last added from item.
     */
    public function columnAliases(string ...$aliases): static
    {
        $from = $this->parts->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);
        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $item->alias, $item->lateral, array_values($aliases));

        return $this->derive(static::class, from: $from);
    }
}
