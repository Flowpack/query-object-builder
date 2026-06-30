<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * A single bound of a window frame: `CURRENT ROW`, `UNBOUNDED PRECEDING`,
 * `UNBOUNDED FOLLOWING`, or an offset form `expr PRECEDING` / `expr FOLLOWING`.
 *
 * @internal
 */
final class FrameBound
{
    private function __construct(
        public readonly string $keyword,
        public readonly ?Exp $offset = null,
    ) {
    }

    public static function currentRow(): self
    {
        return new self('CURRENT ROW');
    }

    public static function unboundedPreceding(): self
    {
        return new self('UNBOUNDED PRECEDING');
    }

    public static function unboundedFollowing(): self
    {
        return new self('UNBOUNDED FOLLOWING');
    }

    public static function preceding(Exp $offset): self
    {
        return new self('PRECEDING', $offset);
    }

    public static function following(Exp $offset): self
    {
        return new self('FOLLOWING', $offset);
    }

    public function writeSql(SqlBuilder $sb): void
    {
        // An offset bound renders the expression before the PRECEDING/FOLLOWING keyword.
        if ($this->offset !== null) {
            $this->offset->writeSql($sb);
            $sb->writeString(' ');
        }
        $sb->writeString($this->keyword);
    }
}
