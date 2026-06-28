<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * The builder state right after {@see SelectSelectBuilder::distinct()}, where
 * {@see on()} restricts the DISTINCT to the given expressions (`DISTINCT ON`).
 */
final class SelectDistinctBuilder extends SelectBuilder
{
    /**
     * Restrict DISTINCT to the given expressions (`DISTINCT ON (...)`).
     */
    public function on(Exp $exp, Exp ...$exps): SelectBuilder
    {
        return $this->derive(SelectBuilder::class, distinctOn: array_values([$exp, ...$exps]));
    }
}
