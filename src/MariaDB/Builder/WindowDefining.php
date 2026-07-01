<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AbstractSelectBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FrameBound;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FrameUnits;
use Flowpack\QueryObjectBuilder\MySQL\Builder\NamedWindow;
use Flowpack\QueryObjectBuilder\MySQL\Builder\OrderByClause;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WindowDefinition;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WindowFrame;

/**
 * Shared building blocks for MariaDB's `WINDOW` clause builders
 * ({@see WindowSelectBuilder} and {@see OrderByWindowSelectBuilder}): both refine
 * the most recently named window. Reconstructing the {@see NamedWindow} happens
 * here in {@see deriveWindow()} alone.
 *
 * @internal
 * @phpstan-require-extends AbstractSelectBuilder
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
     * Bound the current window's frame in `ROWS` units.
     */
    public function rows(FrameBound $start, ?FrameBound $end = null): WindowSelectBuilder
    {
        return $this->withWindowFrame(new WindowFrame(FrameUnits::Rows, $start, $end));
    }

    /**
     * Bound the current window's frame in `RANGE` units.
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
     * @template T of AbstractSelectBuilder
     * @param class-string<T> $class
     * @return T
     */
    protected function deriveWindow(string $class, WindowDefinition $definition): AbstractSelectBuilder
    {
        $windows = $this->parts->windows;
        $lastIdx = array_key_last($windows);
        assert($lastIdx !== null);

        $windows[$lastIdx] = new NamedWindow($windows[$lastIdx]->name, $definition);

        return $this->derive($class, windows: $windows);
    }
}
