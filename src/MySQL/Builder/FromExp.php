<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Marker interface for something that can appear in a FROM clause (a table
 * name, a function call, a subquery, a join, ...).
 *
 * It intentionally does not extend {@see Exp}: not everything that can appear
 * in a FROM clause is a general expression usable elsewhere.
 */
interface FromExp extends SqlWriter
{
}
