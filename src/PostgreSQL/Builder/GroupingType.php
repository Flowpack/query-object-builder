<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

enum GroupingType: string
{
    case Rollup = 'ROLLUP';
    case Cube = 'CUBE';
    case GroupingSets = 'GROUPING SETS';
}
