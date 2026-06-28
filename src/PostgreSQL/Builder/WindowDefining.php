<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Shared building blocks for the `WINDOW` clause builders
 * ({@see WindowSelectBuilder} and {@see OrderByWindowSelectBuilder}): both refine
 * the most recently named window. Reconstructing the {@see NamedWindow} happens
 * here in {@see deriveWindow()} alone.
 *
 * @internal
 * @phpstan-require-extends SelectBuilder
 */
trait WindowDefining
{
    /**
     * Add an ORDER BY expression to the current window (refine via
     * {@see OrderByWindowSelectBuilder}).
     */
    public function orderBy(Exp $exp): OrderByWindowSelectBuilder
    {
        $def = $this->lastWindowDefinition();

        return $this->deriveWindow(OrderByWindowSelectBuilder::class, new WindowDefinition(
            $def->existingWindowName,
            $def->partitionBy,
            [...$def->orderBys, new OrderByClause($exp)],
        ));
    }

    protected function lastWindowDefinition(): WindowDefinition
    {
        $windows = $this->parts->windows;
        $lastIdx = array_key_last($windows);
        assert($lastIdx !== null);

        return $windows[$lastIdx]->definition;
    }

    /**
     * Return a builder of the given type with the last named window's definition
     * replaced.
     *
     * @template T of SelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function deriveWindow(string $class, WindowDefinition $definition): SelectBuilder
    {
        $windows = $this->parts->windows;
        $lastIdx = array_key_last($windows);
        assert($lastIdx !== null);

        $windows[$lastIdx] = new NamedWindow($windows[$lastIdx]->name, $definition);

        return $this->derive($class, windows: $windows);
    }
}
