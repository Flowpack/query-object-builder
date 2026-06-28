<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

enum WithSearchType: string
{
    case Depth = 'DEPTH';
    case Breadth = 'BREADTH';
}
