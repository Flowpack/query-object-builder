<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AliasesLastFromItem;

/**
 * The builder state right after adding a plain item to the FROM clause, where
 * {@see AliasesLastFromItem::as()} aliases it and
 * {@see AliasesLastFromItem::columnAliases()} names its output columns.
 */
final class FromSelectBuilder extends SelectBuilder
{
    use AliasesLastFromItem;
}
