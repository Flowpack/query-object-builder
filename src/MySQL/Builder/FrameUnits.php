<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

enum FrameUnits: string
{
    case Rows = 'ROWS';
    case Range = 'RANGE';
}
