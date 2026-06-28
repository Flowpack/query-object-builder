<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * An expression that carries an operator precedence, used to decide whether it
 * needs to be wrapped in parentheses when nested inside another operator.
 *
 * @internal
 */
interface Precedencer
{
    public function precedence(): int;
}
