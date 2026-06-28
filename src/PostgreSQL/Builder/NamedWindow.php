<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * A single entry of the `WINDOW` clause: `name AS (window_definition)`.
 *
 * @internal
 */
final class NamedWindow
{
    public function __construct(
        public readonly string $name,
        public readonly WindowDefinition $definition = new WindowDefinition(),
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->name . ' AS ');
        $this->definition->writeSql($sb);
    }
}
