<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

enum SortNulls: string
{
    case First = 'NULLS FIRST';
    case Last = 'NULLS LAST';
}
