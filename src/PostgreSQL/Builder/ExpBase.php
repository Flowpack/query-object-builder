<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Builder;

/**
 * Base class for expressions, providing the chainable operator methods.
 *
 * This is the PHP adaptation of the Go `builder.ExpBase`: in Go the operator
 * methods are promoted onto concrete expressions via struct embedding; here
 * concrete expressions extend this class instead. The left-hand side of every
 * operator is `$this`, so no wrapping/unwrapping is needed.
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
