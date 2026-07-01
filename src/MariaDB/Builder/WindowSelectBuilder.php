<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WindowDefinition;

/**
 * The builder state inside a `WINDOW` clause, where {@see as()},
 * {@see partitionBy()} and {@see WindowDefining::orderBy()} define the window
 * named by the preceding {@see SelectBuilder::window()} call.
 */
class WindowSelectBuilder extends SelectBuilder
{
    use WindowDefining;

    /**
     * Open the window definition. Pass an existing window name to base this window
     * on a previously defined one.
     */
    public function as(string $existingWindowName = ''): self
    {
        $def = $this->lastWindowDefinition();

        return $this->deriveWindow(self::class, new WindowDefinition($existingWindowName, $def->partitionBy, $def->orderBys, $def->frame));
    }

    public function partitionBy(Exp $exp, Exp ...$exps): self
    {
        $def = $this->lastWindowDefinition();

        return $this->deriveWindow(self::class, new WindowDefinition(
            $def->existingWindowName,
            [...$def->partitionBy, $exp, ...array_values($exps)],
            $def->orderBys,
            $def->frame,
        ));
    }
}
