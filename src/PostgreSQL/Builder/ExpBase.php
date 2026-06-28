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

    public function like(Exp $rgt): Exp
    {
        return $this->op('LIKE', $rgt);
    }

    /**
     * Build an `IN (...)` expression. The right-hand side is a subquery or a
     * list of expressions (see {@see Q::exps()} / {@see Q::args()}).
     */
    public function in(SelectOrExpressions $rgt): Exp
    {
        return new InExp($this, 'IN', $rgt);
    }

    public function notIn(SelectOrExpressions $rgt): Exp
    {
        return new InExp($this, 'NOT IN', $rgt);
    }

    public function isNull(): Exp
    {
        return new UnaryExp($this, Precedence::of('IS'), suffix: 'IS NULL');
    }

    public function isNotNull(): Exp
    {
        return new UnaryExp($this, Precedence::of('IS'), suffix: 'IS NOT NULL');
    }
}
