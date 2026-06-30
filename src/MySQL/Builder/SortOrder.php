<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

enum SortOrder: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
