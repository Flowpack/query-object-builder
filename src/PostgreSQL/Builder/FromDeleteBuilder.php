<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The DELETE builder state right after adding a USING item, where {@see as()}
 * aliases that item and {@see columnAliases()} names its output columns.
 */
final class FromDeleteBuilder extends DeleteBuilder
{
    /**
     * Set the alias for the last added USING item.
     */
    public function as(string $alias): self
    {
        $using = $this->using;
        $lastIdx = array_key_last($using);
        assert($lastIdx !== null);

        $item = $using[$lastIdx];
        $using[$lastIdx] = new FromItem($item->from, $alias, $item->lateral, $item->only, $item->columnAliases);

        return $this->derive(self::class, using: $using);
    }

    /**
     * Set the column aliases for the last added USING item.
     */
    public function columnAliases(string ...$aliases): self
    {
        $using = $this->using;
        $lastIdx = array_key_last($using);
        assert($lastIdx !== null);

        $item = $using[$lastIdx];
        $using[$lastIdx] = new FromItem($item->from, $item->alias, $item->lateral, $item->only, array_values($aliases));

        return $this->derive(self::class, using: $using);
    }
}
