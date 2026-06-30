<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

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
            $def->frame,
        ));
    }

    /**
     * Bound the current window's frame in `ROWS` units. Pass one bound for the
     * `ROWS start` form or two for `ROWS BETWEEN start AND end`.
     */
    public function rows(FrameBound $start, ?FrameBound $end = null): WindowSelectBuilder
    {
        return $this->withWindowFrame(new WindowFrame(FrameUnits::Rows, $start, $end));
    }

    /**
     * Bound the current window's frame in `RANGE` units. Pass one bound for the
     * `RANGE start` form or two for `RANGE BETWEEN start AND end`.
     */
    public function range(FrameBound $start, ?FrameBound $end = null): WindowSelectBuilder
    {
        return $this->withWindowFrame(new WindowFrame(FrameUnits::Range, $start, $end));
    }

    private function withWindowFrame(WindowFrame $frame): WindowSelectBuilder
    {
        $def = $this->lastWindowDefinition();

        return $this->deriveWindow(WindowSelectBuilder::class, new WindowDefinition(
            $def->existingWindowName,
            $def->partitionBy,
            $def->orderBys,
            $frame,
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
