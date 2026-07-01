<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after starting a combination (UNION / INTERSECT /
 * EXCEPT). The following query is either built further with the generic clause
 * methods or supplied explicitly via {@see query()}; {@see all()} switches the
 * combination to its `ALL` variant.
 */
final class CombinationBuilder extends SelectBuilder
{
    /**
     * Switch the combination to its `ALL` variant.
     */
    public function all(): static
    {
        return $this->derive(static::class, combinations: $this->rebuildLastCombination(all: true));
    }

    /**
     * Supply the query following the combination.
     */
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
