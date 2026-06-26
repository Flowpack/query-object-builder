<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

enum SortOrder: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
