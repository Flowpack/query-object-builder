<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\PostgreSQL\Q;

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\ExtractExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\JsonBuildObjectBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\WindowFuncBuilder;

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
     * Build an `array_agg(...)` aggregate expression.
     */
    public static function arrayAgg(Exp $exp): AggBuilder
    {
        return new AggBuilder('array_agg', [$exp]);
    }

    /**
     * Build a `bit_and(...)` aggregate expression.
     */
    public static function bitAnd(Exp $exp): AggBuilder
    {
        return new AggBuilder('bit_and', [$exp]);
    }

    /**
     * Build a `bit_or(...)` aggregate expression.
     */
    public static function bitOr(Exp $exp): AggBuilder
    {
        return new AggBuilder('bit_or', [$exp]);
    }

    /**
     * Build a `bit_xor(...)` aggregate expression.
     */
    public static function bitXor(Exp $exp): AggBuilder
    {
        return new AggBuilder('bit_xor', [$exp]);
    }

    /**
     * Build a `bool_and(...)` aggregate expression.
     */
    public static function boolAnd(Exp $exp): AggBuilder
    {
        return new AggBuilder('bool_and', [$exp]);
    }

    /**
     * Build a `bool_or(...)` aggregate expression.
     */
    public static function boolOr(Exp $exp): AggBuilder
    {
        return new AggBuilder('bool_or', [$exp]);
    }

    /**
     * Build a `json_object_agg(key, value)` aggregate expression.
     */
    public static function jsonObjectAgg(Exp $key, Exp $value): AggBuilder
    {
        return new AggBuilder('json_object_agg', [$key, $value]);
    }

    /**
     * Build a `jsonb_object_agg(key, value)` aggregate expression.
     */
    public static function jsonbObjectAgg(Exp $key, Exp $value): AggBuilder
    {
        return new AggBuilder('jsonb_object_agg', [$key, $value]);
    }

    /**
     * Build a `string_agg(value, delimiter)` aggregate expression.
     */
    public static function stringAgg(Exp $value, Exp $delimiter): AggBuilder
    {
        return new AggBuilder('string_agg', [$value, $delimiter]);
    }

    /**
     * Build a `max(...)` aggregate expression.
     */
    public static function max(Exp $exp): AggBuilder
    {
        return new AggBuilder('max', [$exp]);
    }

    /**
     * Build a `min(...)` aggregate expression.
     */
    public static function min(Exp $exp): AggBuilder
    {
        return new AggBuilder('min', [$exp]);
    }

    /**
     * Build a `range_agg(...)` aggregate expression.
     */
    public static function rangeAgg(Exp $value): AggBuilder
    {
        return new AggBuilder('range_agg', [$value]);
    }

    /**
     * Build a `range_intersect_agg(...)` aggregate expression.
     */
    public static function rangeIntersectAgg(Exp $value): AggBuilder
    {
        return new AggBuilder('range_intersect_agg', [$value]);
    }

    /**
     * Build an `xmlagg(...)` aggregate expression.
     */
    public static function xmlagg(Exp $exp): AggBuilder
    {
        return new AggBuilder('xmlagg', [$exp]);
    }

    /**
     * Build a `mode()` ordered-set aggregate expression; combine with
     * {@see AggBuilder::withinGroup()} and {@see AggBuilder::orderBy()}.
     */
    public static function mode(): AggBuilder
    {
        return new AggBuilder('mode', []);
    }

    /**
     * Build a `percentile_cont(fraction)` ordered-set aggregate expression.
     */
    public static function percentileCont(Exp $fraction): AggBuilder
    {
        return new AggBuilder('percentile_cont', [$fraction]);
    }

    /**
     * Build a `percentile_disc(fraction)` ordered-set aggregate expression.
     */
    public static function percentileDisc(Exp $fraction): AggBuilder
    {
        return new AggBuilder('percentile_disc', [$fraction]);
    }

    /**
     * Build a `rank(...)` aggregate / window function expression.
     */
    public static function rank(Exp ...$args): AggBuilder
    {
        return new AggBuilder('rank', array_values($args));
    }

    /**
     * Build a `dense_rank(...)` aggregate / window function expression.
     */
    public static function denseRank(Exp ...$args): AggBuilder
    {
        return new AggBuilder('dense_rank', array_values($args));
    }

    /**
     * Build a `percent_rank(...)` aggregate / window function expression.
     */
    public static function percentRank(Exp ...$args): AggBuilder
    {
        return new AggBuilder('percent_rank', array_values($args));
    }

    /**
     * Build a `cume_dist(...)` aggregate / window function expression.
     */
    public static function cumeDist(Exp ...$args): AggBuilder
    {
        return new AggBuilder('cume_dist', array_values($args));
    }

    /**
     * Build a `GROUPING(...)` aggregate expression.
     */
    public static function grouping(Exp ...$exps): AggBuilder
    {
        return new AggBuilder('GROUPING', array_values($exps));
    }

    /**
     * Build a `row_number()` window function expression.
     */
    public static function rowNumber(): WindowFuncBuilder
    {
        return new WindowFuncBuilder(new FuncExp('row_number', []));
    }

    /**
     * Build a `lower(...)` expression.
     */
    public static function lower(Exp $exp): FuncExp
    {
        return new FuncExp('lower', [$exp]);
    }

    /**
     * Build an `upper(...)` expression.
     */
    public static function upper(Exp $exp): FuncExp
    {
        return new FuncExp('upper', [$exp]);
    }

    /**
     * Build an `initcap(...)` expression.
     */
    public static function initcap(Exp $exp): FuncExp
    {
        return new FuncExp('initcap', [$exp]);
    }

    /**
     * Build an `EXTRACT(field FROM source)` expression.
     */
    public static function extract(string $field, Exp $from): ExtractExp
    {
        return new ExtractExp($field, $from);
    }

    /**
     * Build an `array_append(array, element)` expression.
     */
    public static function arrayAppend(Exp $array, Exp $element): FuncExp
    {
        return new FuncExp('array_append', [$array, $element]);
    }

    /**
     * Build an `array_prepend(element, array)` expression.
     */
    public static function arrayPrepend(Exp $element, Exp $array): FuncExp
    {
        return new FuncExp('array_prepend', [$element, $array]);
    }

    /**
     * Build an `array_cat(array1, array2)` expression.
     */
    public static function arrayCat(Exp $array1, Exp $array2): FuncExp
    {
        return new FuncExp('array_cat', [$array1, $array2]);
    }

    /**
     * Build an `array_dims(array)` expression.
     */
    public static function arrayDims(Exp $array): FuncExp
    {
        return new FuncExp('array_dims', [$array]);
    }

    /**
     * Build an `array_ndims(array)` expression.
     */
    public static function arrayNdims(Exp $array): FuncExp
    {
        return new FuncExp('array_ndims', [$array]);
    }

    /**
     * Build an `array_length(array, dimension)` expression.
     */
    public static function arrayLength(Exp $array, Exp $dimension): FuncExp
    {
        return new FuncExp('array_length', [$array, $dimension]);
    }

    /**
     * Build an `array_lower(array, dimension)` expression.
     */
    public static function arrayLower(Exp $array, Exp $dimension): FuncExp
    {
        return new FuncExp('array_lower', [$array, $dimension]);
    }

    /**
     * Build an `array_upper(array, dimension)` expression.
     */
    public static function arrayUpper(Exp $array, Exp $dimension): FuncExp
    {
        return new FuncExp('array_upper', [$array, $dimension]);
    }

    /**
     * Build an `array_remove(array, element)` expression.
     */
    public static function arrayRemove(Exp $array, Exp $element): FuncExp
    {
        return new FuncExp('array_remove', [$array, $element]);
    }

    /**
     * Build an `array_replace(array, from, to)` expression.
     */
    public static function arrayReplace(Exp $array, Exp $from, Exp $to): FuncExp
    {
        return new FuncExp('array_replace', [$array, $from, $to]);
    }

    /**
     * Build an `array_position(array, element [, start])` expression.
     */
    public static function arrayPosition(Exp $array, Exp $element, ?Exp $start = null): FuncExp
    {
        return new FuncExp('array_position', $start === null ? [$array, $element] : [$array, $element, $start]);
    }

    /**
     * Build an `array_positions(array, element)` expression.
     */
    public static function arrayPositions(Exp $array, Exp $element): FuncExp
    {
        return new FuncExp('array_positions', [$array, $element]);
    }

    /**
     * Build an `array_to_string(array, delimiter [, nullString])` expression.
     */
    public static function arrayToString(Exp $array, Exp $delimiter, ?Exp $nullString = null): FuncExp
    {
        return new FuncExp('array_to_string', $nullString === null ? [$array, $delimiter] : [$array, $delimiter, $nullString]);
    }

    /**
     * Build a `string_to_array(text, delimiter [, nullString])` expression.
     */
    public static function stringToArray(Exp $text, Exp $delimiter, ?Exp $nullString = null): FuncExp
    {
        return new FuncExp('string_to_array', $nullString === null ? [$text, $delimiter] : [$text, $delimiter, $nullString]);
    }

    /**
     * Build an `array_fill(value, dimensions [, lowerBounds])` expression.
     */
    public static function arrayFill(Exp $value, Exp $dimensions, ?Exp $lowerBounds = null): FuncExp
    {
        return new FuncExp('array_fill', $lowerBounds === null ? [$value, $dimensions] : [$value, $dimensions, $lowerBounds]);
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
     * Build a `generate_subscripts(...)` set-returning function.
     */
    public static function generateSubscripts(Exp $array, Exp $dim, ?Exp $reverse = null): FuncBuilder
    {
        return new FuncBuilder('generate_subscripts', $reverse === null ? [$array, $dim] : [$array, $dim, $reverse]);
    }

    /**
     * Build a `json_to_recordset(...)` set-returning function.
     */
    public static function jsonToRecordset(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_to_recordset', [$exp]);
    }

    /**
     * Build a `json_array_elements(...)` set-returning function.
     */
    public static function jsonArrayElements(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_array_elements', [$exp]);
    }

    /**
     * Build a `jsonb_array_elements(...)` set-returning function.
     */
    public static function jsonbArrayElements(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('jsonb_array_elements', [$exp]);
    }

    /**
     * Build a `json_array_elements_text(...)` set-returning function.
     */
    public static function jsonArrayElementsText(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_array_elements_text', [$exp]);
    }

    /**
     * Build a `jsonb_array_elements_text(...)` set-returning function.
     */
    public static function jsonbArrayElementsText(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('jsonb_array_elements_text', [$exp]);
    }

    /**
     * Build a `json_each(...)` set-returning function.
     */
    public static function jsonEach(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_each', [$exp]);
    }

    /**
     * Build a `jsonb_each(...)` set-returning function.
     */
    public static function jsonbEach(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('jsonb_each', [$exp]);
    }

    /**
     * Build a `json_each_text(...)` set-returning function.
     */
    public static function jsonEachText(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_each_text', [$exp]);
    }

    /**
     * Build a `jsonb_each_text(...)` set-returning function.
     */
    public static function jsonbEachText(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('jsonb_each_text', [$exp]);
    }

    /**
     * Build a `json_object_keys(...)` set-returning function.
     */
    public static function jsonObjectKeys(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('json_object_keys', [$exp]);
    }

    /**
     * Build a `jsonb_object_keys(...)` set-returning function.
     */
    public static function jsonbObjectKeys(Exp $exp): FuncBuilder
    {
        return new FuncBuilder('jsonb_object_keys', [$exp]);
    }

    /**
     * Build a `json_populate_recordset(base, fromJson)` set-returning function.
     */
    public static function jsonPopulateRecordset(Exp $base, Exp $fromJson): FuncBuilder
    {
        return new FuncBuilder('json_populate_recordset', [$base, $fromJson]);
    }

    /**
     * Build a `jsonb_populate_recordset(base, fromJson)` set-returning function.
     */
    public static function jsonbPopulateRecordset(Exp $base, Exp $fromJson): FuncBuilder
    {
        return new FuncBuilder('jsonb_populate_recordset', [$base, $fromJson]);
    }

    /**
     * Build a `jsonb_path_query(target, path [, options ...])` set-returning function.
     */
    public static function jsonbPathQuery(Exp $target, Exp $path, Exp ...$options): FuncBuilder
    {
        return new FuncBuilder('jsonb_path_query', array_values([$target, $path, ...$options]));
    }

    /**
     * Build a `jsonb_path_query_tz(target, path [, options ...])` set-returning function.
     */
    public static function jsonbPathQueryTz(Exp $target, Exp $path, Exp ...$options): FuncBuilder
    {
        return new FuncBuilder('jsonb_path_query_tz', array_values([$target, $path, ...$options]));
    }
}
