<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Q;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WindowFuncBuilder;

/**
 * Facade for SQL function expressions, accessed as `Q\Func`.
 */
final class Func
{
    private function __construct()
    {
    }

    // Aggregate functions — usable on their own or, via {@see AggBuilder::over()},
    // as window functions.

    /**
     * Build a `COUNT(expr)` aggregate (pass `Q::n('*')` for `COUNT(*)`).
     */
    public static function count(Exp $expr): AggBuilder
    {
        return new AggBuilder('COUNT', [$expr]);
    }

    /**
     * Build a `SUM(expr)` aggregate.
     */
    public static function sum(Exp $expr): AggBuilder
    {
        return new AggBuilder('SUM', [$expr]);
    }

    /**
     * Build an `AVG(expr)` aggregate.
     */
    public static function avg(Exp $expr): AggBuilder
    {
        return new AggBuilder('AVG', [$expr]);
    }

    /**
     * Build a `MIN(expr)` aggregate.
     */
    public static function min(Exp $expr): AggBuilder
    {
        return new AggBuilder('MIN', [$expr]);
    }

    /**
     * Build a `MAX(expr)` aggregate.
     */
    public static function max(Exp $expr): AggBuilder
    {
        return new AggBuilder('MAX', [$expr]);
    }

    // Nonaggregate window functions — each requires an `OVER` clause; call
    // {@see WindowFuncBuilder::over()} to add it.

    /**
     * The `ROW_NUMBER()` window function: the sequential row number within the
     * window partition.
     */
    public static function rowNumber(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('ROW_NUMBER', []));
    }

    /**
     * The `RANK()` window function: the rank within the partition, with gaps after
     * ties.
     */
    public static function rank(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('RANK', []));
    }

    /**
     * The `DENSE_RANK()` window function: the rank within the partition, without
     * gaps after ties.
     */
    public static function denseRank(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('DENSE_RANK', []));
    }

    /**
     * The `PERCENT_RANK()` window function: the relative rank as a value in
     * `[0, 1]`.
     */
    public static function percentRank(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('PERCENT_RANK', []));
    }

    /**
     * The `CUME_DIST()` window function: the cumulative distribution of the current
     * row within the partition.
     */
    public static function cumeDist(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('CUME_DIST', []));
    }

    /**
     * The `NTILE(n)` window function: the bucket number when the partition is split
     * into `n` buckets.
     */
    public static function ntile(Exp $buckets): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('NTILE', [$buckets]));
    }

    /**
     * The `LAG(expr[, offset[, default]])` window function: the value `offset` rows
     * before the current row within the partition.
     */
    public static function lag(Exp $expr, ?Exp $offset = null, ?Exp $default = null): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('LAG', self::leadLagArgs($expr, $offset, $default)));
    }

    /**
     * The `LEAD(expr[, offset[, default]])` window function: the value `offset` rows
     * after the current row within the partition.
     */
    public static function lead(Exp $expr, ?Exp $offset = null, ?Exp $default = null): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('LEAD', self::leadLagArgs($expr, $offset, $default)));
    }

    /**
     * The `FIRST_VALUE(expr)` window function: the value of `expr` in the first row
     * of the window frame.
     */
    public static function firstValue(Exp $expr): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('FIRST_VALUE', [$expr]));
    }

    /**
     * The `LAST_VALUE(expr)` window function: the value of `expr` in the last row
     * of the window frame.
     */
    public static function lastValue(Exp $expr): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('LAST_VALUE', [$expr]));
    }

    /**
     * The `NTH_VALUE(expr, n)` window function: the value of `expr` in the `n`-th
     * row of the window frame.
     */
    public static function nthValue(Exp $expr, Exp $n): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('NTH_VALUE', [$expr, $n]));
    }

    /**
     * Build the argument list for LAG / LEAD: the default is only meaningful when an
     * offset is given, so it is dropped unless the offset is present.
     *
     * @return list<Exp>
     */
    private static function leadLagArgs(Exp $expr, ?Exp $offset, ?Exp $default): array
    {
        $args = [$expr];
        if ($offset !== null) {
            $args[] = $offset;
            if ($default !== null) {
                $args[] = $default;
            }
        }

        return $args;
    }
}
