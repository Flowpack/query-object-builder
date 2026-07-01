<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Base class for expressions, providing the chainable operator methods with
 * `$this` as their left-hand side — the infix and postfix operators of the SQL
 * dialect (comparison, arithmetic, `LIKE`, `REGEXP`, `IN`, `IS NULL`, the JSON
 * path operators, ...).
 *
 * Functions such as `CONCAT`, `POW`, `CAST` or `JSON_CONTAINS` read as function
 * calls in SQL, so they are constructed through the facade ({@see Q}) rather than
 * chained here.
 */
abstract class ExpBase implements Exp
{
    /**
     * Combine this expression with another using an arbitrary operator.
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

    /**
     * Null-safe equality (`a <=> b`): like `=`, but `NULL <=> NULL` is true.
     */
    public function nullSafeEq(Exp $rgt): OpExp
    {
        return $this->op('<=>', $rgt);
    }

    // Arithmetic operators

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

    // Bitwise operators — the infix operator form. (The like-named aggregates
    // `BIT_AND` / `BIT_OR` / `BIT_XOR` are built via {@see Q\Func}.)

    /**
     * Bitwise AND (`a & b`).
     */
    public function bitAnd(Exp $rgt): OpExp
    {
        return $this->op('&', $rgt);
    }

    /**
     * Bitwise OR (`a | b`).
     */
    public function bitOr(Exp $rgt): OpExp
    {
        return $this->op('|', $rgt);
    }

    /**
     * Bitwise XOR (`a ^ b`).
     */
    public function bitXor(Exp $rgt): OpExp
    {
        return $this->op('^', $rgt);
    }

    /**
     * Left bit shift (`a << b`).
     */
    public function shiftLeft(Exp $rgt): OpExp
    {
        return $this->op('<<', $rgt);
    }

    /**
     * Right bit shift (`a >> b`).
     */
    public function shiftRight(Exp $rgt): OpExp
    {
        return $this->op('>>', $rgt);
    }

    // JSON path operators

    /**
     * Extract a JSON value at the given path (`a -> '$.path'`). The right-hand
     * side is a JSON path string literal (e.g. `'$.name'`, `'$[0]'`).
     */
    public function jsonExtract(Exp $rgt): OpExp
    {
        return new OpExp($this, '->', $rgt, requires: Requirement::mysql());
    }

    /**
     * Extract and unquote a JSON value at the given path (`a ->> '$.path'`).
     */
    public function jsonExtractText(Exp $rgt): OpExp
    {
        return new OpExp($this, '->>', $rgt, requires: Requirement::mysql());
    }

    // Pattern matching

    public function like(Exp $rgt): Exp
    {
        return $this->op('LIKE', $rgt);
    }

    public function notLike(Exp $rgt): Exp
    {
        return $this->op('NOT LIKE', $rgt);
    }

    /**
     * Regular-expression match (`a REGEXP b`). Case sensitivity follows the
     * operand collation.
     */
    public function regexp(Exp $rgt): Exp
    {
        return $this->op('REGEXP', $rgt);
    }

    public function notRegexp(Exp $rgt): Exp
    {
        return $this->op('NOT REGEXP', $rgt);
    }

    /**
     * Build an `IN (...)` expression. The right-hand side is a subquery or a
     * list of expressions (see {@see Expressions}).
     */
    public function in(SelectOrExpressions $rgt): Exp
    {
        return new InExp($this, 'IN', $rgt);
    }

    public function notIn(SelectOrExpressions $rgt): Exp
    {
        return new InExp($this, 'NOT IN', $rgt);
    }

    /**
     * JSON array membership (`value MEMBER OF (json_array)`): whether this value is
     * an element of the given JSON array.
     */
    public function memberOf(Exp $jsonArray): Exp
    {
        return new MemberOfExp($this, $jsonArray);
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
