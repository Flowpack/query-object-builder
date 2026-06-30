<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A window frame clause: `ROWS`/`RANGE` followed by a single bound or a
 * `BETWEEN start AND end` extent.
 *
 * @internal
 */
final class WindowFrame
{
    public function __construct(
        public readonly FrameUnits $units,
        public readonly FrameBound $start,
        public readonly ?FrameBound $end = null,
    ) {
    }

    public function writeSql(SqlBuilder $sb): void
    {
        $sb->writeString($this->units->value . ' ');

        // A second bound turns the frame into a BETWEEN ... AND ... extent.
        if ($this->end !== null) {
            $sb->writeString('BETWEEN ');
            $this->start->writeSql($sb);
            $sb->writeString(' AND ');
            $this->end->writeSql($sb);
        } else {
            $this->start->writeSql($sb);
        }
    }
}
