<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The GROUP BY refinement adding super-aggregate rollup rows. Shared by both
 * dialects' `GroupBySelectBuilder`.
 *
 * @internal
 * @phpstan-require-extends AbstractSelectBuilder
 */
trait AppliesRollup
{
    /**
     * Add super-aggregate rows over the grouping (`GROUP BY ... WITH ROLLUP`).
     */
    public function withRollup(): static
    {
        return $this->derive(static::class, groupByWithRollup: true);
    }
}
