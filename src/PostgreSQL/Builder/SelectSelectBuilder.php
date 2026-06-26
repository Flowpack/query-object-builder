<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding expressions to the select list.
 *
 * Here {@see as()} aliases the last added select expression.
 */
final class SelectSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the last added select expression.
     */
    public function as(string $alias): self
    {
        $parts = clone $this->parts;
        $lastIdx = array_key_last($parts->selectList);
        assert($lastIdx !== null);

        $output = clone $parts->selectList[$lastIdx];
        $output->alias = $alias;
        $parts->selectList[$lastIdx] = $output;

        return $this->into(self::class, $parts);
    }
}
