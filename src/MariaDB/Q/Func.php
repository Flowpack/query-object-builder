<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MariaDB\Q;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\MySQL\Q\SharedFunctions;

/**
 * Facade for MariaDB SQL function expressions, accessed as `Q\Func`.
 *
 * The dialect-agnostic function set lives in the shared {@see SharedFunctions} trait;
 * this facade adds functions specific to MariaDB.
 */
final class Func
{
    use SharedFunctions;

    private function __construct()
    {
    }

    // JSON

    /** `JSON_QUERY(doc, path)` — the object or array at the given path. */
    public static function jsonQuery(Exp $doc, Exp $path): FuncExp
    {
        return self::call('JSON_QUERY', $doc, $path);
    }

    /** `JSON_EXISTS(doc, path)` — whether a value exists at the given path. */
    public static function jsonExists(Exp $doc, Exp $path): FuncExp
    {
        return self::call('JSON_EXISTS', $doc, $path);
    }

    /** `JSON_DETAILED(doc)` — pretty-print a JSON document. */
    public static function jsonDetailed(Exp $doc): FuncExp
    {
        return self::call('JSON_DETAILED', $doc);
    }

    // Aggregate / window

    /** `MEDIAN(expr)` — the median value; use with `OVER (...)` via {@see AggBuilder::over()}. */
    public static function median(Exp $expr): AggBuilder
    {
        return self::agg('MEDIAN', $expr);
    }

    // Oracle-compatibility functions

    /** `TO_CHAR(expr)` or `TO_CHAR(expr, format)`. */
    public static function toChar(Exp $expr, Exp ...$format): FuncExp
    {
        return self::call('TO_CHAR', $expr, ...$format);
    }

    /** `ADD_MONTHS(date, months)`. */
    public static function addMonths(Exp $date, Exp $months): FuncExp
    {
        return self::call('ADD_MONTHS', $date, $months);
    }

    /** `MONTHS_BETWEEN(a, b)`. */
    public static function monthsBetween(Exp $a, Exp $b): FuncExp
    {
        return self::call('MONTHS_BETWEEN', $a, $b);
    }

    /** `CHR(n)` — the character for the given code point. */
    public static function chr(Exp $n): FuncExp
    {
        return self::call('CHR', $n);
    }

    /** `OCT(n)` — the octal string for the given number. */
    public static function oct(Exp $n): FuncExp
    {
        return self::call('OCT', $n);
    }
}
