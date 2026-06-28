<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An array literal expression, e.g. `ARRAY[1, 2, 3]`.
 *
 * All elements should be of the same type.
 */
final class ArrayExp extends ExpBase
{
    /**
     * @param list<Exp> $elems
     */
    public function __construct(
        private readonly array $elems,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString('ARRAY[');
        foreach ($this->elems as $i => $elem) {
            if ($i > 0) {
                $sb->writeString(',');
            }
            $elem->writeSql($sb);
        }
        $sb->writeString(']');
    }
}
