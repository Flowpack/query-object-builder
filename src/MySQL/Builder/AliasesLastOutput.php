<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Refinements available right after adding select expressions: aliasing the last
 * output expression and marking the select `DISTINCT`. Shared by both dialects'
 * `SelectSelectBuilder`.
 *
 * @internal
 * @phpstan-require-extends AbstractSelectBuilder
 */
trait AliasesLastOutput
{
    /**
     * Set the output alias for the last added select expression.
     */
    public function as(string $alias): static
    {
        $selectList = $this->parts->selectList;
        $lastIdx = array_key_last($selectList);
        assert($lastIdx !== null);
        $selectList[$lastIdx] = new OutputExpr($selectList[$lastIdx]->exp, $alias);

        return $this->derive(static::class, selectList: $selectList);
    }

    /**
     * Make the select `DISTINCT`.
     */
    public function distinct(): static
    {
        return $this->derive(static::class, distinct: true);
    }
}
