<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The builder state right after adding a plain item to the FROM clause, where
 * {@see as()} aliases the last added from item and {@see columnAliases()} names
 * its output columns.
 */
final class FromSelectBuilder extends SelectBuilder
{
    use AliasesLastFromItem;
}
