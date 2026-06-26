<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

enum JoinType: string
{
    case Inner = 'JOIN';
    case Left = 'LEFT JOIN';
    case Right = 'RIGHT JOIN';
    case Full = 'FULL JOIN';
    case Cross = 'CROSS JOIN';
}
