<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * MySQL/MariaDB join types. There is no FULL OUTER JOIN.
 */
enum JoinType: string
{
    case Inner = 'JOIN';
    case Left = 'LEFT JOIN';
    case Right = 'RIGHT JOIN';
    case Cross = 'CROSS JOIN';
}
