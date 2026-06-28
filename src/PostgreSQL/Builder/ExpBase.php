<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Base class for expressions, providing the chainable operator methods with
 * `$this` as their left-hand side.
 */
abstract class ExpBase implements Exp
{
    /**
     * Combine this expression with another using an arbitrary operator.
     *
     * Example: `Q::n('a')->op('^', Q::int(5))`
     */
    public function op(string $op, Exp $rgt): OpExp
    {
        return new OpExp($this, $op, $rgt);
    }

    public function eq(Exp $rgt): Exp
    {
        return $this->op('=', $rgt);
    }

    public function neq(Exp $rgt): Exp
    {
        return $this->op('<>', $rgt);
    }

    public function lt(Exp $rgt): Exp
    {
        return $this->op('<', $rgt);
    }

    public function lte(Exp $rgt): Exp
    {
        return $this->op('<=', $rgt);
    }

    public function gt(Exp $rgt): Exp
    {
        return $this->op('>', $rgt);
    }

    public function gte(Exp $rgt): Exp
    {
        return $this->op('>=', $rgt);
    }
}
