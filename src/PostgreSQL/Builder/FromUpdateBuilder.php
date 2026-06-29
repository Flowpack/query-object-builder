<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The UPDATE builder state right after adding a FROM item, where {@see as()}
 * aliases that item and {@see columnAliases()} names its output columns.
 */
final class FromUpdateBuilder extends UpdateBuilder
{
    /**
     * Set the alias for the last added FROM item.
     */
    public function as(string $alias): self
    {
        $from = $this->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);

        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $alias, $item->lateral, $item->only, $item->columnAliases);

        return $this->derive(self::class, from: $from);
    }

    /**
     * Set the column aliases for the last added FROM item.
     */
    public function columnAliases(string ...$aliases): self
    {
        $from = $this->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);

        $item = $from[$lastIdx];
        $from[$lastIdx] = new FromItem($item->from, $item->alias, $item->lateral, $item->only, array_values($aliases));

        return $this->derive(self::class, from: $from);
    }
}
