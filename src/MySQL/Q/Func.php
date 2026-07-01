<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Q;

use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;

/**
 * Facade for MySQL SQL function expressions, accessed as `Q\Func`.
 *
 * The dialect-agnostic function set lives in {@see SharedFunctions}; this facade
 * adds MySQL-only functions (absent on MariaDB).
 */
final class Func
{
    use SharedFunctions;

    private function __construct()
    {
    }

    /** `REGEXP_LIKE(subject, pattern)` or `REGEXP_LIKE(subject, pattern, matchType)`. */
    public static function regexpLike(Exp $subject, Exp $pattern, Exp ...$matchType): FuncExp
    {
        return self::call('REGEXP_LIKE', $subject, $pattern, ...$matchType);
    }

    /** `GROUPING(expr, ...)` — distinguishes super-aggregate `WITH ROLLUP` NULLs. */
    public static function grouping(Exp $expr, Exp ...$rest): FuncExp
    {
        return self::call('GROUPING', $expr, ...$rest);
    }

    /** `ANY_VALUE(expr)` — suppress `ONLY_FULL_GROUP_BY` rejection for a column. */
    public static function anyValue(Exp $expr): FuncExp
    {
        return self::call('ANY_VALUE', $expr);
    }

    /** `JSON_SCHEMA_VALID(schema, doc)`. */
    public static function jsonSchemaValid(Exp $schema, Exp $doc): FuncExp
    {
        return self::call('JSON_SCHEMA_VALID', $schema, $doc);
    }

    /** `JSON_SCHEMA_VALIDATION_REPORT(schema, doc)`. */
    public static function jsonSchemaValidationReport(Exp $schema, Exp $doc): FuncExp
    {
        return self::call('JSON_SCHEMA_VALIDATION_REPORT', $schema, $doc);
    }

    /** `JSON_STORAGE_SIZE(doc)`. */
    public static function jsonStorageSize(Exp $doc): FuncExp
    {
        return self::call('JSON_STORAGE_SIZE', $doc);
    }

    /** `JSON_STORAGE_FREE(doc)`. */
    public static function jsonStorageFree(Exp $doc): FuncExp
    {
        return self::call('JSON_STORAGE_FREE', $doc);
    }

    /** `JSON_PRETTY(doc)` — pretty-print a JSON document. */
    public static function jsonPretty(Exp $doc): FuncExp
    {
        return self::call('JSON_PRETTY', $doc);
    }

    /** `RANDOM_BYTES(len)` — a string of cryptographically strong random bytes. */
    public static function randomBytes(Exp $len): FuncExp
    {
        return self::call('RANDOM_BYTES', $len);
    }
}
