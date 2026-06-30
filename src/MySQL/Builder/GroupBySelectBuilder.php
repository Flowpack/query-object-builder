<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after a GROUP BY, where {@see withRollup()} adds the
 * super-aggregate rollup rows.
 */
final class GroupBySelectBuilder extends SelectBuilder
{
    /**
     * Add super-aggregate rows over the grouping (`GROUP BY ... WITH ROLLUP`).
     */
    public function withRollup(): self
    {
        return $this->derive(self::class, groupByWithRollup: true);
    }
}
