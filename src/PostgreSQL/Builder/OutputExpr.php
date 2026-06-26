<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single entry in the select list: an expression with an optional alias.
 *
 * Mutable by design: the immutable builders copy it (via clone) before changing
 * its alias, see {@see SelectSelectBuilder::as()}.
 */
final class OutputExpr
{
    public function __construct(
        public Exp $exp,
        public string $alias = '',
    ) {
    }
}
