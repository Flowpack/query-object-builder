<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single item of a `RETURNING` clause: an output expression with an optional
 * output name. (Available on the MariaDB dialect only.)
 *
 * @internal
 */
final class ReturningItem
{
    public function __construct(
        public readonly Exp $outputExpression,
        public readonly string $outputName = '',
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $this->outputExpression->writeSql($sb);
        if ($this->outputName !== '') {
            $sb->writeString(' AS ' . $this->outputName);
        }
    }
}
