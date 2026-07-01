<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Refinements for the last combination (UNION / INTERSECT / EXCEPT): switching it
 * to its `ALL` variant and supplying the following query. Shared by both dialects'
 * `CombinationBuilder`.
 *
 * @internal
 * @phpstan-require-extends SelectBuilder
 */
trait RefinesCombination
{
    public function all(): static
    {
        return $this->derive(static::class, combinations: $this->rebuildLastCombination(all: true));
    }

    public function query(SelectBuilder $query): static
    {
        return $this->derive(static::class, combinations: $this->rebuildLastCombination(query: $query));
    }

    /**
     * Return the combinations with the last one replaced by a copy carrying the
     * given overrides.
     *
     * @return list<Combination>
     */
    private function rebuildLastCombination(?bool $all = null, ?SelectBuilder $query = null): array
    {
        $combinations = $this->combinations;
        $lastIdx = array_key_last($combinations);
        assert($lastIdx !== null);

        $c = $combinations[$lastIdx];
        $combinations[$lastIdx] = new Combination($c->parts, $c->type, $all ?? $c->all, $query ?? $c->query);

        return $combinations;
    }
}
