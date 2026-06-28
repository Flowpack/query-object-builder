<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A named argument expression. Calling {@see Q::bind()} again with the same name
 * reuses the same positional placeholder; the value is bound later via
 * {@see QueryBuilder::withNamedArgs()}.
 */
final class BindExp extends ExpBase
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($sb->bindPlaceholder($this->name));
    }
}
