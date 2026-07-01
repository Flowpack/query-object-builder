<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A named argument expression; bind its value via {@see QueryBuilder::withNamedArgs()}.
 *
 * Because MySQL `?` placeholders are positional and not reusable, reusing the
 * same name emits another placeholder bound to the same value.
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
