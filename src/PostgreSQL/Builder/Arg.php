<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An argument expression: a value bound to a positional placeholder.
 *
 * Each instance creates a new placeholder (e.g. `$1`) when the query is built.
 *
 * Port of the Go `builder.argExp` / `Arg`.
 */
final class Arg extends ExpBase
{
    public function __construct(
        private readonly mixed $argument,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($sb->createPlaceholder($this->argument));
    }
}
