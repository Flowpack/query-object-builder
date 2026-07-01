<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * The supported join types.
 */
enum JoinType: string
{
    case Inner = 'JOIN';
    case Left = 'LEFT JOIN';
    case Right = 'RIGHT JOIN';
    case Cross = 'CROSS JOIN';
}
