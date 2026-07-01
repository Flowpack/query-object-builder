<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Q;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Dialect;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;

/**
 * Facade for SQL function expressions, accessed as `Q\Func`.
 *
 * The bulk of the set lives in {@see SharedFunctions}. This facade adds the
 * functions only one engine of the MySQL family provides; each marks itself while
 * rendering, so validating the query against a {@see \Flowpack\QueryObjectBuilder\MySQL\Builder\Target}
 * reports the ones the target cannot express.
 */
final class Func
{
    use SharedFunctions;

    private function __construct()
    {
    }

    // MySQL-only functions

    /** `REGEXP_LIKE(subject, pattern)` or `REGEXP_LIKE(subject, pattern, matchType)`. */
    public static function regexpLike(Exp $subject, Exp $pattern, Exp ...$matchType): FuncExp
    {
        return self::gated(Dialect::Mysql, 'REGEXP_LIKE', $subject, $pattern, ...$matchType);
    }

    /** `GROUPING(expr, ...)` — distinguishes super-aggregate `WITH ROLLUP` NULLs. */
    public static function grouping(Exp $expr, Exp ...$rest): FuncExp
    {
        return self::gated(Dialect::Mysql, 'GROUPING', $expr, ...$rest);
    }

    /** `ANY_VALUE(expr)` — suppress `ONLY_FULL_GROUP_BY` rejection for a column. */
    public static function anyValue(Exp $expr): FuncExp
    {
        return self::gated(Dialect::Mysql, 'ANY_VALUE', $expr);
    }

    /** `JSON_SCHEMA_VALID(schema, doc)`. */
    public static function jsonSchemaValid(Exp $schema, Exp $doc): FuncExp
    {
        return self::gated(Dialect::Mysql, 'JSON_SCHEMA_VALID', $schema, $doc);
    }

    /** `JSON_SCHEMA_VALIDATION_REPORT(schema, doc)`. */
    public static function jsonSchemaValidationReport(Exp $schema, Exp $doc): FuncExp
    {
        return self::gated(Dialect::Mysql, 'JSON_SCHEMA_VALIDATION_REPORT', $schema, $doc);
    }

    /** `JSON_STORAGE_SIZE(doc)`. */
    public static function jsonStorageSize(Exp $doc): FuncExp
    {
        return self::gated(Dialect::Mysql, 'JSON_STORAGE_SIZE', $doc);
    }

    /** `JSON_STORAGE_FREE(doc)`. */
    public static function jsonStorageFree(Exp $doc): FuncExp
    {
        return self::gated(Dialect::Mysql, 'JSON_STORAGE_FREE', $doc);
    }

    /** `JSON_PRETTY(doc)` — pretty-print a JSON document. */
    public static function jsonPretty(Exp $doc): FuncExp
    {
        return self::gated(Dialect::Mysql, 'JSON_PRETTY', $doc);
    }

    /** `RANDOM_BYTES(len)` — a string of cryptographically strong random bytes. */
    public static function randomBytes(Exp $len): FuncExp
    {
        return self::gated(Dialect::Mysql, 'RANDOM_BYTES', $len);
    }

    // MariaDB-only functions

    /** `JSON_QUERY(doc, path)` — the object or array at the given path. */
    public static function jsonQuery(Exp $doc, Exp $path): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'JSON_QUERY', $doc, $path);
    }

    /** `JSON_EXISTS(doc, path)` — whether a value exists at the given path. */
    public static function jsonExists(Exp $doc, Exp $path): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'JSON_EXISTS', $doc, $path);
    }

    /** `JSON_DETAILED(doc)` — pretty-print a JSON document. */
    public static function jsonDetailed(Exp $doc): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'JSON_DETAILED', $doc);
    }

    /** `MEDIAN(expr)` — the median value; use with `OVER (...)` via {@see AggBuilder::over()}. */
    public static function median(Exp $expr): AggBuilder
    {
        return self::gatedAgg(Dialect::MariaDb, 'MEDIAN', $expr);
    }

    /** `TO_CHAR(expr)` or `TO_CHAR(expr, format)`. */
    public static function toChar(Exp $expr, Exp ...$format): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'TO_CHAR', $expr, ...$format);
    }

    /** `ADD_MONTHS(date, months)`. */
    public static function addMonths(Exp $date, Exp $months): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'ADD_MONTHS', $date, $months);
    }

    /** `MONTHS_BETWEEN(a, b)`. */
    public static function monthsBetween(Exp $a, Exp $b): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'MONTHS_BETWEEN', $a, $b);
    }

    /** `CHR(n)` — the character for the given code point. */
    public static function chr(Exp $n): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'CHR', $n);
    }

    /** `OCT(n)` — the octal string for the given number. */
    public static function oct(Exp $n): FuncExp
    {
        return self::gated(Dialect::MariaDb, 'OCT', $n);
    }
}
