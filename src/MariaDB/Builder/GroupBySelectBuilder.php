<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AppliesRollup;

/**
 * The builder state right after a GROUP BY, where {@see AppliesRollup::withRollup()}
 * adds the super-aggregate rollup rows.
 */
final class GroupBySelectBuilder extends SelectBuilder
{
    use AppliesRollup;
}
