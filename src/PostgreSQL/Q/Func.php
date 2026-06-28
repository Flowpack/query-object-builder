<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Q;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;

/**
 * Facade for SQL function expressions, accessed as `Q\Func`.
 */
final class Func
{
    private function __construct()
    {
    }

    /**
     * Build a `json_build_object(...)` expression.
     */
    public static function jsonBuildObject(): JsonBuildObjectBuilder
    {
        return new JsonBuildObjectBuilder(false);
    }

    /**
     * Build a `jsonb_build_object(...)` expression.
     */
    public static function jsonbBuildObject(): JsonBuildObjectBuilder
    {
        return new JsonBuildObjectBuilder(true);
    }

    /**
     * Build a `json_agg(...)` aggregate expression.
     */
    public static function jsonAgg(Exp $exp): AggBuilder
    {
        return new AggBuilder('json_agg', [$exp]);
    }

    /**
     * Build a `jsonb_agg(...)` aggregate expression.
     */
    public static function jsonbAgg(Exp $exp): AggBuilder
    {
        return new AggBuilder('jsonb_agg', [$exp]);
    }

    /**
     * Build a `count(...)` aggregate expression.
     */
    public static function count(Exp $exp): AggBuilder
    {
        return new AggBuilder('count', [$exp]);
    }

    /**
     * Build a `sum(...)` aggregate expression.
     */
    public static function sum(Exp $exp): AggBuilder
    {
        return new AggBuilder('sum', [$exp]);
    }

    /**
     * Build an `avg(...)` aggregate expression.
     */
    public static function avg(Exp $exp): AggBuilder
    {
        return new AggBuilder('avg', [$exp]);
    }

    /**
     * Build a `lower(...)` expression.
     */
    public static function lower(Exp $exp): FuncExp
    {
        return new FuncExp('lower', [$exp]);
    }

    /**
     * Build an `unnest(...)` set-returning function.
     */
    public static function unnest(Exp $array, Exp ...$arrays): FuncBuilder
    {
        return new FuncBuilder('unnest', array_values([$array, ...$arrays]));
    }

    /**
     * Build a `generate_series(...)` set-returning function.
     */
    public static function generateSeries(Exp $start, Exp $stop, ?Exp $step = null): FuncBuilder
    {
        return new FuncBuilder('generate_series', $step === null ? [$start, $stop] : [$start, $stop, $step]);
    }

    /**
     * Build a `json_to_recordset(...)` set-returning function.
     */
    public static function jsonToRecordset(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_to_recordset', [$exp]);
    }
}
