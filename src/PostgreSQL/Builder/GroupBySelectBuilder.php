<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after {@see SelectBuilder::groupBy()}, exposing the
 * special grouping elements: {@see empty()} (`GROUP BY ()`), {@see distinct()}
 * (`GROUP BY DISTINCT`), and {@see rollup()} / {@see cube()} /
 * {@see groupingSets()}.
 */
final class GroupBySelectBuilder extends SelectBuilder
{
    /**
     * Add an empty grouping element (`( )`).
     */
    public function empty(): self
    {
        return $this->addGrouping(null, [[]]);
    }

    /**
     * Make the GROUP BY use the DISTINCT modifier.
     */
    public function distinct(): self
    {
        return $this->derive(self::class, groupByDistinct: true);
    }

    public function rollup(Expressions ...$sets): self
    {
        return $this->addGrouping(GroupingType::Rollup, self::unwrap(array_values($sets)));
    }

    public function cube(Expressions ...$sets): self
    {
        return $this->addGrouping(GroupingType::Cube, self::unwrap(array_values($sets)));
    }

    public function groupingSets(Expressions ...$sets): self
    {
        return $this->addGrouping(GroupingType::GroupingSets, self::unwrap(array_values($sets)));
    }

    /**
     * @param list<Expressions> $sets
     * @return list<list<Exp>>
     */
    private static function unwrap(array $sets): array
    {
        return array_map(static fn (Expressions $e): array => $e->exps, $sets);
    }

    /**
     * @param list<list<Exp>> $sets
     */
    private function addGrouping(?GroupingType $type, array $sets): self
    {
        return $this->derive(self::class, groupBys: [...$this->parts->groupBys, new GroupingElement($sets, $type)]);
    }
}
