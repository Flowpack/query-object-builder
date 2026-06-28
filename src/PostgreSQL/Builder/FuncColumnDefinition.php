<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A column definition for a set-returning function used in a FROM clause,
 * e.g. the `a INTEGER` in `... AS (a INTEGER, b TEXT)`.
 *
 * @internal
 */
final class FuncColumnDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
    }
}
