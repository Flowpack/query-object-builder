<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Marker interface for the right-hand side of an `IN` / `NOT IN`: either a
 * subquery (a {@see SelectBuilder}) or a parenthesized list of expressions
 * (an {@see Expressions}). Both render their own surrounding parentheses.
 *
 * @internal
 */
interface SelectOrExpressions extends Exp
{
}
