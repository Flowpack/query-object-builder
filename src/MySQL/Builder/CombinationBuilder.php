<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after starting a combination (UNION / INTERSECT /
 * EXCEPT). The following query is either built further with the generic clause
 * methods or supplied explicitly via {@see query()}; {@see all()} switches the
 * combination to its `ALL` variant.
 */
final class CombinationBuilder extends SelectBuilder
{
    use RefinesCombination;
}
