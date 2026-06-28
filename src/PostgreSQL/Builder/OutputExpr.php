<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A select-list entry: an expression with an optional output alias.
 *
 * @internal
 */
final class OutputExpr
{
    public function __construct(
        public readonly Exp $exp,
        public readonly string $alias = '',
    ) {
    }
}
