<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

enum CombinationType: string
{
    case Union = 'UNION';
    case Intersect = 'INTERSECT';
    case Except = 'EXCEPT';
}
