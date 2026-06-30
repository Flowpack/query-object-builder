<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * An argument expression: a value bound to a positional placeholder.
 *
 * Each instance creates a new positional `?` placeholder when the query is built.
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
