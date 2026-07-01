<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Refinements for the last join: its alias, `ON` condition and `USING` columns.
 * Reconstructing the {@see Join} happens here in {@see rebuildLastJoin()} alone.
 * Shared by both dialects' `JoinSelectBuilder`.
 *
 * @internal
 * @phpstan-require-extends AbstractSelectBuilder
 */
trait RefinesLastJoin
{
    /**
     * Set the alias for the last added join.
     */
    public function as(string $alias): static
    {
        return $this->derive(static::class, from: $this->rebuildLastJoin(alias: $alias));
    }

    /**
     * Set the ON condition for the last added join.
     */
    public function on(Exp $cond): static
    {
        return $this->derive(static::class, from: $this->rebuildLastJoin(on: $cond));
    }

    /**
     * Set the USING columns for the last added join.
     */
    public function using(string ...$columns): static
    {
        return $this->derive(static::class, from: $this->rebuildLastJoin(using: array_values($columns)));
    }

    /**
     * Return the from list with the last join replaced by a copy carrying the
     * given overrides.
     *
     * @param list<string>|null $using
     * @return list<FromItem>
     */
    private function rebuildLastJoin(?string $alias = null, ?Exp $on = null, ?array $using = null): array
    {
        $from = $this->parts->from;
        $lastIdx = array_key_last($from);
        assert($lastIdx !== null);

        $join = $from[$lastIdx]->from;
        assert($join instanceof Join);

        $from[$lastIdx] = new FromItem(new Join(
            $join->joinType,
            $join->lateral,
            $join->from,
            $alias ?? $join->alias,
            $on ?? $join->on,
            $using ?? $join->using,
        ));

        return $from;
    }
}
