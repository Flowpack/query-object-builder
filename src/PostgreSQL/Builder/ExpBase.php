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

    public function isDistinctFrom(Exp $rgt): Exp
    {
        return $this->op('IS DISTINCT FROM', $rgt);
    }

    public function isNotDistinctFrom(Exp $rgt): Exp
    {
        return $this->op('IS NOT DISTINCT FROM', $rgt);
    }

    /**
     * Cast to the given type (`expr::type`).
     */
    public function cast(string $type): OpExp
    {
        return new OpExp($this, '::', new TypeExp($type), unspaced: true);
    }

    /**
     * Array subscript `expr[index]` or slice `expr[lower:upper]`.
     */
    public function subscript(Exp $index, ?Exp $upperBound = null): SubscriptExp
    {
        return new SubscriptExp($this, $index, $upperBound);
    }

    // Math operators

    public function plus(Exp $rgt): OpExp
    {
        return $this->op('+', $rgt);
    }

    public function minus(Exp $rgt): OpExp
    {
        return $this->op('-', $rgt);
    }

    public function mult(Exp $rgt): OpExp
    {
        return $this->op('*', $rgt);
    }

    public function divide(Exp $rgt): OpExp
    {
        return $this->op('/', $rgt);
    }

    public function mod(Exp $rgt): OpExp
    {
        return $this->op('%', $rgt);
    }

    public function pow(Exp $rgt): OpExp
    {
        return $this->op('^', $rgt);
    }

    // JSON operators

    public function jsonExtract(Exp $rgt): OpExp
    {
        return $this->op('->', $rgt);
    }

    public function jsonExtractText(Exp $rgt): OpExp
    {
        return $this->op('->>', $rgt);
    }

    public function jsonExtractPath(Exp $rgt): OpExp
    {
        return $this->op('#>', $rgt);
    }

    public function jsonExtractPathText(Exp $rgt): OpExp
    {
        return $this->op('#>>', $rgt);
    }

    public function contains(Exp $rgt): OpExp
    {
        return $this->op('@>', $rgt);
    }

    public function containedBy(Exp $rgt): OpExp
    {
        return $this->op('<@', $rgt);
    }

    // POSIX regular expression matching

    public function regexpMatch(Exp $rgt): Exp
    {
        return $this->op('~', $rgt);
    }

    public function regexpIMatch(Exp $rgt): Exp
    {
        return $this->op('~*', $rgt);
    }

    public function regexpNotMatch(Exp $rgt): Exp
    {
        return $this->op('!~', $rgt);
    }

    public function regexpINotMatch(Exp $rgt): Exp
    {
        return $this->op('!~*', $rgt);
    }

    public function concat(Exp $rgt): Exp
    {
        return $this->op('||', $rgt);
    }

    public function like(Exp $rgt): Exp
    {
        return $this->op('LIKE', $rgt);
    }

    public function ilike(Exp $rgt): Exp
    {
        return $this->op('ILIKE', $rgt);
    }

    public function notLike(Exp $rgt): Exp
    {
        return $this->op('NOT LIKE', $rgt);
    }

    public function notILike(Exp $rgt): Exp
    {
        return $this->op('NOT ILIKE', $rgt);
    }

    public function similarTo(Exp $rgt): Exp
    {
        return $this->op('SIMILAR TO', $rgt);
    }

    public function notSimilarTo(Exp $rgt): Exp
    {
        return $this->op('NOT SIMILAR TO', $rgt);
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
