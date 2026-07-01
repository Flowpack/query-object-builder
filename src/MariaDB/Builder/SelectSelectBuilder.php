<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Builder;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AliasesLastOutput;

/**
 * The builder state right after adding expressions to the select list, where
 * {@see AliasesLastOutput::as()} aliases the last added select expression and
 * {@see AliasesLastOutput::distinct()} marks the select as DISTINCT.
 */
final class SelectSelectBuilder extends SelectBuilder
{
    use AliasesLastOutput;
}
