<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The DELETE builder state right after joining a table, where {@see as()},
 * {@see on()} and {@see using()} act on that last join.
 */
final class JoinDeleteBuilder extends DeleteBuilder
{
    /**
     * Set the alias for the last added join.
     */
    public function as(string $alias): static
    {
        return $this->derive(static::class, joins: $this->rebuildLastJoin(alias: $alias));
    }

    /**
     * Set the ON condition for the last added join.
     */
    public function on(Exp $cond): static
    {
        return $this->derive(static::class, joins: $this->rebuildLastJoin(on: $cond));
    }

    /**
     * Set the USING columns for the last added join.
     */
    public function using(string ...$columns): static
    {
        return $this->derive(static::class, joins: $this->rebuildLastJoin(using: array_values($columns)));
    }

    /**
     * Return the join list with the last join replaced by a copy carrying the
     * given overrides.
     *
     * @param list<string>|null $using
     * @return list<FromItem>
     */
    private function rebuildLastJoin(?string $alias = null, ?Exp $on = null, ?array $using = null): array
    {
        $joins = $this->joins;
        $lastIdx = array_key_last($joins);
        assert($lastIdx !== null);

        $join = $joins[$lastIdx]->from;
        assert($join instanceof Join);

        $joins[$lastIdx] = new FromItem(new Join(
            $join->joinType,
            $join->lateral,
            $join->from,
            $alias ?? $join->alias,
            $on ?? $join->on,
            $using ?? $join->using,
        ));

        return $joins;
    }
}
