<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding expressions to the select list, where
 * {@see as()} aliases the last added select expression and {@see distinct()}
 * marks the select as DISTINCT.
 */
final class SelectSelectBuilder extends SelectBuilder
{
    use AliasesLastOutput;
}
