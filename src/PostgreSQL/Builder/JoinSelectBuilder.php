<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after adding a join to the FROM clause.
 *
 * Here {@see as()}, {@see on()} and {@see using()} act on the last added join.
 */
final class JoinSelectBuilder extends SelectBuilder
{
    /**
     * Set the alias for the last added join.
     */
    public function as(string $alias): self
    {
        [$parts, $join] = $this->cloneLastJoin();
        $join->alias = $alias;

        return $this->into(self::class, $parts);
    }

    /**
     * Set the ON condition for the last added join.
     */
    public function on(Exp $cond): SelectBuilder
    {
        [$parts, $join] = $this->cloneLastJoin();
        $join->on = $cond;

        return $this->into(SelectBuilder::class, $parts);
    }

    /**
     * Set the USING columns for the last added join.
     */
    public function using(string ...$columns): SelectBuilder
    {
        [$parts, $join] = $this->cloneLastJoin();
        $join->using = array_values($columns);

        return $this->into(SelectBuilder::class, $parts);
    }

    /**
     * Clone the parts together with the last from item and its join, wired up so
     * the returned join can be mutated in place without affecting this builder.
     *
     * @return array{0: SelectQueryParts, 1: Join}
     */
    private function cloneLastJoin(): array
    {
        $parts = clone $this->parts;
        $lastIdx = array_key_last($parts->from);
        assert($lastIdx !== null);

        $fromItem = clone $parts->from[$lastIdx];
        $join = $fromItem->from;
        assert($join instanceof Join);
        $join = clone $join;

        $fromItem->from = $join;
        $parts->from[$lastIdx] = $fromItem;

        return [$parts, $join];
    }
}
