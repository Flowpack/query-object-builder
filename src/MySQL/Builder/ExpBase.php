<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Builder;

/**
 * Base class for expressions, providing the chainable operator methods with
 * `$this` as their left-hand side.
 *
 * Several operations that PostgreSQL spells as operators are functions in
 * MySQL/MariaDB: concatenation (`CONCAT`), power (`POW`), cast (`CAST`),
 * JSON containment (`JSON_CONTAINS`). `||` would mean logical OR and `^`
 * bitwise XOR here, so they are not used.
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
     * Null-safe equality (`a <=> b`); the MySQL/MariaDB equivalent of the SQL
     * standard `IS NOT DISTINCT FROM`.
     */
    public function nullSafeEq(Exp $rgt): OpExp
    {
        return $this->op('<=>', $rgt);
    }

    public function isNotDistinctFrom(Exp $rgt): Exp
    {
        return $this->op('<=>', $rgt);
    }

    public function isDistinctFrom(Exp $rgt): Exp
    {
        return new UnaryExp($this->op('<=>', $rgt), Precedence::of('NOT'), prefix: 'NOT');
    }

    /**
     * Cast to the given type (`CAST(expr AS type)`). There is no `::` operator.
     */
    public function cast(string $type): CastExp
    {
        return new CastExp($this, new TypeExp($type));
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

    /**
     * Power. Rendered as `POW(a, b)` — `^` is bitwise XOR in MySQL/MariaDB.
     */
    public function pow(Exp $rgt): FuncExp
    {
        return new FuncExp('POW', [$this, $rgt]);
    }

    /**
     * String concatenation. Rendered as `CONCAT(a, b)` — `||` is logical OR
     * under the default sql_mode. Note `CONCAT` returns NULL if any argument is NULL.
     */
    public function concat(Exp $rgt): FuncExp
    {
        return new FuncExp('CONCAT', [$this, $rgt]);
    }

    // JSON

    /**
     * Extract a JSON value at the given path (`a -> '$.path'`). The right-hand
     * side is a JSON path string literal, not a key/index expression.
     */
    public function jsonExtract(Exp $rgt): OpExp
    {
        return $this->op('->', $rgt);
    }

    /**
     * Extract and unquote a JSON value at the given path (`a ->> '$.path'`).
     */
    public function jsonExtractText(Exp $rgt): OpExp
    {
        return $this->op('->>', $rgt);
    }

    /**
     * JSON containment (`JSON_CONTAINS(a, candidate)`).
     */
    public function jsonContains(Exp $candidate): FuncExp
    {
        return new FuncExp('JSON_CONTAINS', [$this, $candidate]);
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
     * operand collation (case-insensitive by default).
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

    public function isNull(): Exp
    {
        return new UnaryExp($this, Precedence::of('IS'), suffix: 'IS NULL');
    }

    public function isNotNull(): Exp
    {
        return new UnaryExp($this, Precedence::of('IS'), suffix: 'IS NOT NULL');
    }
}
