<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\RefinesCombination;

/**
 * The builder state right after starting a combination (UNION / INTERSECT /
 * EXCEPT). The following query is either built further with the generic clause
 * methods or supplied explicitly via {@see RefinesCombination::query()};
 * {@see RefinesCombination::all()} switches the combination to its `ALL` variant.
 */
final class CombinationBuilder extends SelectBuilder
{
    use RefinesCombination;
}
