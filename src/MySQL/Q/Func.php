<?php

declare(strict_types=1);

namespace Flowpack\QueryObjectBuilder\MySQL\Q;

use Flowpack\QueryObjectBuilder\MySQL\Builder\AggBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Dialect;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\ExtractExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\FuncExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\GroupConcatBuilder;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Keyword;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Requirement;
use Flowpack\QueryObjectBuilder\MySQL\Builder\TrimExp;
use Flowpack\QueryObjectBuilder\MySQL\Builder\WindowFuncBuilder;

/**
 * Facade for SQL function expressions, accessed as `Q\Func`: aggregates, the
 * string / regexp / numeric / date-time / JSON / misc scalar families, the window
 * functions and the special-shape builders, plus the functions only one engine of
 * the MySQL family provides. Each dialect-only function marks itself while
 * rendering, so validating the query against a {@see \Flowpack\QueryObjectBuilder\MySQL\Builder\Target}
 * reports the ones the target cannot express.
 */
final class Func
{
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

    /**
     * Build a `GROUP_CONCAT(...)` aggregate. Refine with
     * {@see GroupConcatBuilder::distinct()} / {@see GroupConcatBuilder::orderBy()} /
     * {@see GroupConcatBuilder::separator()}.
     */
    public static function groupConcat(Exp $expr, Exp ...$rest): GroupConcatBuilder
    {
        return new GroupConcatBuilder(array_values([$expr, ...$rest]));
    }

    /**
     * Build a `JSON_ARRAYAGG(expr)` aggregate (collects values into a JSON array).
     */
    public static function jsonArrayAgg(Exp $expr): AggBuilder
    {
        return self::agg('JSON_ARRAYAGG', $expr);
    }

    /**
     * Build a `JSON_OBJECTAGG(key, value)` aggregate (collects pairs into a JSON object).
     */
    public static function jsonObjectAgg(Exp $key, Exp $value): AggBuilder
    {
        return self::agg('JSON_OBJECTAGG', $key, $value);
    }

    /**
     * Build a `BIT_AND(expr)` aggregate (bitwise AND of all values).
     */
    public static function bitAnd(Exp $expr): AggBuilder
    {
        return self::agg('BIT_AND', $expr);
    }

    /**
     * Build a `BIT_OR(expr)` aggregate (bitwise OR of all values).
     */
    public static function bitOr(Exp $expr): AggBuilder
    {
        return self::agg('BIT_OR', $expr);
    }

    /**
     * Build a `BIT_XOR(expr)` aggregate (bitwise XOR of all values).
     */
    public static function bitXor(Exp $expr): AggBuilder
    {
        return self::agg('BIT_XOR', $expr);
    }

    /**
     * Build a `STDDEV_POP(expr)` aggregate (population standard deviation).
     */
    public static function stddevPop(Exp $expr): AggBuilder
    {
        return self::agg('STDDEV_POP', $expr);
    }

    /**
     * Build a `STDDEV_SAMP(expr)` aggregate (sample standard deviation).
     */
    public static function stddevSamp(Exp $expr): AggBuilder
    {
        return self::agg('STDDEV_SAMP', $expr);
    }

    /**
     * Build a `VAR_POP(expr)` aggregate (population variance).
     */
    public static function varPop(Exp $expr): AggBuilder
    {
        return self::agg('VAR_POP', $expr);
    }

    /**
     * Build a `VAR_SAMP(expr)` aggregate (sample variance).
     */
    public static function varSamp(Exp $expr): AggBuilder
    {
        return self::agg('VAR_SAMP', $expr);
    }

    // Conditional functions

    /**
     * Build an `IF(condition, then, else)` expression.
     */
    public static function if(Exp $condition, Exp $then, Exp $else): FuncExp
    {
        return new FuncExp('IF', [$condition, $then, $else]);
    }

    /**
     * Build an `IFNULL(a, b)` expression (returns `b` when `a` is NULL).
     */
    public static function ifnull(Exp $a, Exp $b): FuncExp
    {
        return new FuncExp('IFNULL', [$a, $b]);
    }

    // Date / time functions with special shapes

    /**
     * Build an `EXTRACT(unit FROM source)` expression (e.g. `extract('YEAR', $d)`).
     */
    public static function extract(string $unit, Exp $from): ExtractExp
    {
        return new ExtractExp($unit, $from);
    }

    /**
     * Build a `DATE_ADD(date, interval)` expression. Pass the interval via
     * `Q::interval(...)` (e.g. `dateAdd($d, Q::interval(Q::int(1), 'DAY'))`).
     */
    public static function dateAdd(Exp $date, Exp $interval): FuncExp
    {
        return new FuncExp('DATE_ADD', [$date, $interval]);
    }

    /**
     * Build a `DATE_SUB(date, interval)` expression. Pass the interval via
     * `Q::interval(...)`.
     */
    public static function dateSub(Exp $date, Exp $interval): FuncExp
    {
        return new FuncExp('DATE_SUB', [$date, $interval]);
    }

    // String trimming

    /**
     * Build a `TRIM(str)` expression (removes leading and trailing spaces).
     */
    public static function trim(Exp $str): TrimExp
    {
        return new TrimExp($str);
    }

    /**
     * Build a `TRIM(LEADING remstr FROM str)` expression.
     */
    public static function trimLeading(Exp $remstr, Exp $str): TrimExp
    {
        return new TrimExp($str, 'LEADING', $remstr);
    }

    /**
     * Build a `TRIM(TRAILING remstr FROM str)` expression.
     */
    public static function trimTrailing(Exp $remstr, Exp $str): TrimExp
    {
        return new TrimExp($str, 'TRAILING', $remstr);
    }

    /**
     * Build a `TRIM(BOTH remstr FROM str)` expression.
     */
    public static function trimBoth(Exp $remstr, Exp $str): TrimExp
    {
        return new TrimExp($str, 'BOTH', $remstr);
    }

    // String functions

    /** `CONCAT(...)` — concatenate the arguments. */
    public static function concat(Exp $expr, Exp ...$rest): FuncExp
    {
        return self::call('CONCAT', $expr, ...$rest);
    }

    /** `CONCAT_WS(separator, ...)` — concatenate with a separator, skipping NULLs. */
    public static function concatWs(Exp $separator, Exp $expr, Exp ...$rest): FuncExp
    {
        return self::call('CONCAT_WS', $separator, $expr, ...$rest);
    }

    /** `LOWER(str)`. */
    public static function lower(Exp $str): FuncExp
    {
        return self::call('LOWER', $str);
    }

    /** `UPPER(str)`. */
    public static function upper(Exp $str): FuncExp
    {
        return self::call('UPPER', $str);
    }

    /** `LENGTH(str)` — length in bytes. */
    public static function length(Exp $str): FuncExp
    {
        return self::call('LENGTH', $str);
    }

    /** `CHAR_LENGTH(str)` — length in characters. */
    public static function charLength(Exp $str): FuncExp
    {
        return self::call('CHAR_LENGTH', $str);
    }

    /** `SUBSTRING(str, pos)` or `SUBSTRING(str, pos, len)`. */
    public static function substring(Exp $str, Exp $pos, ?Exp $len = null): FuncExp
    {
        return $len === null ? self::call('SUBSTRING', $str, $pos) : self::call('SUBSTRING', $str, $pos, $len);
    }

    /** `LEFT(str, len)`. */
    public static function left(Exp $str, Exp $len): FuncExp
    {
        return self::call('LEFT', $str, $len);
    }

    /** `RIGHT(str, len)`. */
    public static function right(Exp $str, Exp $len): FuncExp
    {
        return self::call('RIGHT', $str, $len);
    }

    /** `LTRIM(str)` — remove leading spaces. */
    public static function ltrim(Exp $str): FuncExp
    {
        return self::call('LTRIM', $str);
    }

    /** `RTRIM(str)` — remove trailing spaces. */
    public static function rtrim(Exp $str): FuncExp
    {
        return self::call('RTRIM', $str);
    }

    /** `LPAD(str, len, pad)`. */
    public static function lpad(Exp $str, Exp $len, Exp $pad): FuncExp
    {
        return self::call('LPAD', $str, $len, $pad);
    }

    /** `RPAD(str, len, pad)`. */
    public static function rpad(Exp $str, Exp $len, Exp $pad): FuncExp
    {
        return self::call('RPAD', $str, $len, $pad);
    }

    /** `REPLACE(str, from, to)` — replace all occurrences of a substring. */
    public static function replace(Exp $str, Exp $from, Exp $to): FuncExp
    {
        return self::call('REPLACE', $str, $from, $to);
    }

    /** `REPEAT(str, count)`. */
    public static function repeat(Exp $str, Exp $count): FuncExp
    {
        return self::call('REPEAT', $str, $count);
    }

    /** `REVERSE(str)`. */
    public static function reverse(Exp $str): FuncExp
    {
        return self::call('REVERSE', $str);
    }

    /** `LOCATE(substr, str)` or `LOCATE(substr, str, pos)`. */
    public static function locate(Exp $substr, Exp $str, ?Exp $pos = null): FuncExp
    {
        return $pos === null ? self::call('LOCATE', $substr, $str) : self::call('LOCATE', $substr, $str, $pos);
    }

    /** `INSTR(str, substr)`. */
    public static function instr(Exp $str, Exp $substr): FuncExp
    {
        return self::call('INSTR', $str, $substr);
    }

    /** `SUBSTRING_INDEX(str, delim, count)`. */
    public static function substringIndex(Exp $str, Exp $delim, Exp $count): FuncExp
    {
        return self::call('SUBSTRING_INDEX', $str, $delim, $count);
    }

    /** `FIELD(needle, ...)` — the 1-based index of needle in the argument list. */
    public static function field(Exp $needle, Exp ...$haystack): FuncExp
    {
        return self::call('FIELD', $needle, ...$haystack);
    }

    /** `FIND_IN_SET(needle, set)` — index of needle in a comma-separated string. */
    public static function findInSet(Exp $needle, Exp $set): FuncExp
    {
        return self::call('FIND_IN_SET', $needle, $set);
    }

    /** `FORMAT(num, decimals)` or `FORMAT(num, decimals, locale)`. */
    public static function format(Exp $num, Exp $decimals, ?Exp $locale = null): FuncExp
    {
        return $locale === null ? self::call('FORMAT', $num, $decimals) : self::call('FORMAT', $num, $decimals, $locale);
    }

    /** `HEX(n_or_str)`. */
    public static function hex(Exp $expr): FuncExp
    {
        return self::call('HEX', $expr);
    }

    /** `UNHEX(str)`. */
    public static function unhex(Exp $str): FuncExp
    {
        return self::call('UNHEX', $str);
    }

    // Regular-expression functions (match via the `REGEXP` operator on expressions)

    /** `REGEXP_REPLACE(subject, pattern, replacement, ...)`. */
    public static function regexpReplace(Exp $subject, Exp $pattern, Exp $replacement, Exp ...$rest): FuncExp
    {
        return self::call('REGEXP_REPLACE', $subject, $pattern, $replacement, ...$rest);
    }

    /** `REGEXP_INSTR(subject, pattern, ...)`. */
    public static function regexpInstr(Exp $subject, Exp $pattern, Exp ...$rest): FuncExp
    {
        return self::call('REGEXP_INSTR', $subject, $pattern, ...$rest);
    }

    /** `REGEXP_SUBSTR(subject, pattern, ...)`. */
    public static function regexpSubstr(Exp $subject, Exp $pattern, Exp ...$rest): FuncExp
    {
        return self::call('REGEXP_SUBSTR', $subject, $pattern, ...$rest);
    }

    // Numeric functions

    /** `ABS(n)`. */
    public static function abs(Exp $n): FuncExp
    {
        return self::call('ABS', $n);
    }

    /** `CEIL(n)`. */
    public static function ceil(Exp $n): FuncExp
    {
        return self::call('CEIL', $n);
    }

    /** `FLOOR(n)`. */
    public static function floor(Exp $n): FuncExp
    {
        return self::call('FLOOR', $n);
    }

    /** `ROUND(n)` or `ROUND(n, decimals)`. */
    public static function round(Exp $n, ?Exp $decimals = null): FuncExp
    {
        return $decimals === null ? self::call('ROUND', $n) : self::call('ROUND', $n, $decimals);
    }

    /** `TRUNCATE(n, decimals)`. */
    public static function truncate(Exp $n, Exp $decimals): FuncExp
    {
        return self::call('TRUNCATE', $n, $decimals);
    }

    /** `MOD(a, b)`. */
    public static function mod(Exp $a, Exp $b): FuncExp
    {
        return self::call('MOD', $a, $b);
    }

    /** `POWER(base, exponent)`. */
    public static function power(Exp $base, Exp $exponent): FuncExp
    {
        return self::call('POWER', $base, $exponent);
    }

    /** `SQRT(n)`. */
    public static function sqrt(Exp $n): FuncExp
    {
        return self::call('SQRT', $n);
    }

    /** `EXP(n)`. */
    public static function exp(Exp $n): FuncExp
    {
        return self::call('EXP', $n);
    }

    /** `LN(n)` — natural logarithm. */
    public static function ln(Exp $n): FuncExp
    {
        return self::call('LN', $n);
    }

    /** `LOG(n)` (natural) or `LOG(base, n)`. */
    public static function log(Exp $arg, Exp ...$rest): FuncExp
    {
        return self::call('LOG', $arg, ...$rest);
    }

    /** `LOG2(n)`. */
    public static function log2(Exp $n): FuncExp
    {
        return self::call('LOG2', $n);
    }

    /** `LOG10(n)`. */
    public static function log10(Exp $n): FuncExp
    {
        return self::call('LOG10', $n);
    }

    /** `SIGN(n)`. */
    public static function sign(Exp $n): FuncExp
    {
        return self::call('SIGN', $n);
    }

    /** `RAND()` or `RAND(seed)`. */
    public static function rand(Exp ...$seed): FuncExp
    {
        return self::call('RAND', ...$seed);
    }

    /** `PI()`. */
    public static function pi(): FuncExp
    {
        return self::call('PI');
    }

    /** `SIN(n)`. */
    public static function sin(Exp $n): FuncExp
    {
        return self::call('SIN', $n);
    }

    /** `COS(n)`. */
    public static function cos(Exp $n): FuncExp
    {
        return self::call('COS', $n);
    }

    /** `TAN(n)`. */
    public static function tan(Exp $n): FuncExp
    {
        return self::call('TAN', $n);
    }

    /** `COT(n)`. */
    public static function cot(Exp $n): FuncExp
    {
        return self::call('COT', $n);
    }

    /** `ASIN(n)`. */
    public static function asin(Exp $n): FuncExp
    {
        return self::call('ASIN', $n);
    }

    /** `ACOS(n)`. */
    public static function acos(Exp $n): FuncExp
    {
        return self::call('ACOS', $n);
    }

    /** `ATAN(n)` or `ATAN(y, x)`. */
    public static function atan(Exp $arg, Exp ...$rest): FuncExp
    {
        return self::call('ATAN', $arg, ...$rest);
    }

    /** `ATAN2(y, x)`. */
    public static function atan2(Exp $y, Exp $x): FuncExp
    {
        return self::call('ATAN2', $y, $x);
    }

    /** `RADIANS(degrees)`. */
    public static function radians(Exp $degrees): FuncExp
    {
        return self::call('RADIANS', $degrees);
    }

    /** `DEGREES(radians)`. */
    public static function degrees(Exp $radians): FuncExp
    {
        return self::call('DEGREES', $radians);
    }

    // Date / time functions

    /** `NOW()` — the current date and time. */
    public static function now(): FuncExp
    {
        return self::call('NOW');
    }

    /** `CURDATE()` — the current date. */
    public static function curdate(): FuncExp
    {
        return self::call('CURDATE');
    }

    /** `CURTIME()` — the current time. */
    public static function curtime(): FuncExp
    {
        return self::call('CURTIME');
    }

    /** `CURRENT_TIMESTAMP()`. */
    public static function currentTimestamp(): FuncExp
    {
        return self::call('CURRENT_TIMESTAMP');
    }

    /** `UTC_TIMESTAMP()`. */
    public static function utcTimestamp(): FuncExp
    {
        return self::call('UTC_TIMESTAMP');
    }

    /** `DATE(expr)` — the date part of a datetime. */
    public static function date(Exp $expr): FuncExp
    {
        return self::call('DATE', $expr);
    }

    /** `TIME(expr)` — the time part of a datetime. */
    public static function time(Exp $expr): FuncExp
    {
        return self::call('TIME', $expr);
    }

    /** `YEAR(date)`. */
    public static function year(Exp $date): FuncExp
    {
        return self::call('YEAR', $date);
    }

    /** `MONTH(date)`. */
    public static function month(Exp $date): FuncExp
    {
        return self::call('MONTH', $date);
    }

    /** `DAY(date)`. */
    public static function day(Exp $date): FuncExp
    {
        return self::call('DAY', $date);
    }

    /** `HOUR(time)`. */
    public static function hour(Exp $time): FuncExp
    {
        return self::call('HOUR', $time);
    }

    /** `MINUTE(time)`. */
    public static function minute(Exp $time): FuncExp
    {
        return self::call('MINUTE', $time);
    }

    /** `SECOND(time)`. */
    public static function second(Exp $time): FuncExp
    {
        return self::call('SECOND', $time);
    }

    /** `QUARTER(date)`. */
    public static function quarter(Exp $date): FuncExp
    {
        return self::call('QUARTER', $date);
    }

    /** `WEEK(date)` or `WEEK(date, mode)`. */
    public static function week(Exp $date, Exp ...$mode): FuncExp
    {
        return self::call('WEEK', $date, ...$mode);
    }

    /** `DAYOFWEEK(date)`. */
    public static function dayOfWeek(Exp $date): FuncExp
    {
        return self::call('DAYOFWEEK', $date);
    }

    /** `DAYOFYEAR(date)`. */
    public static function dayOfYear(Exp $date): FuncExp
    {
        return self::call('DAYOFYEAR', $date);
    }

    /** `DAYNAME(date)`. */
    public static function dayName(Exp $date): FuncExp
    {
        return self::call('DAYNAME', $date);
    }

    /** `MONTHNAME(date)`. */
    public static function monthName(Exp $date): FuncExp
    {
        return self::call('MONTHNAME', $date);
    }

    /** `LAST_DAY(date)` — the last day of the month. */
    public static function lastDay(Exp $date): FuncExp
    {
        return self::call('LAST_DAY', $date);
    }

    /** `DATEDIFF(a, b)` — the number of days between two dates. */
    public static function dateDiff(Exp $a, Exp $b): FuncExp
    {
        return self::call('DATEDIFF', $a, $b);
    }

    /** `TIMESTAMPDIFF(unit, a, b)` — the difference in the given unit. */
    public static function timestampDiff(string $unit, Exp $a, Exp $b): FuncExp
    {
        return self::call('TIMESTAMPDIFF', new Keyword($unit), $a, $b);
    }

    /** `TIMESTAMPADD(unit, interval, datetime)`. */
    public static function timestampAdd(string $unit, Exp $interval, Exp $datetime): FuncExp
    {
        return self::call('TIMESTAMPADD', new Keyword($unit), $interval, $datetime);
    }

    /** `DATE_FORMAT(date, format)`. */
    public static function dateFormat(Exp $date, Exp $format): FuncExp
    {
        return self::call('DATE_FORMAT', $date, $format);
    }

    /** `STR_TO_DATE(str, format)`. */
    public static function strToDate(Exp $str, Exp $format): FuncExp
    {
        return self::call('STR_TO_DATE', $str, $format);
    }

    /** `UNIX_TIMESTAMP()` or `UNIX_TIMESTAMP(date)`. */
    public static function unixTimestamp(Exp ...$date): FuncExp
    {
        return self::call('UNIX_TIMESTAMP', ...$date);
    }

    /** `FROM_UNIXTIME(ts)` or `FROM_UNIXTIME(ts, format)`. */
    public static function fromUnixtime(Exp $ts, Exp ...$format): FuncExp
    {
        return self::call('FROM_UNIXTIME', $ts, ...$format);
    }

    /** `CONVERT_TZ(dt, fromTz, toTz)`. */
    public static function convertTz(Exp $dt, Exp $fromTz, Exp $toTz): FuncExp
    {
        return self::call('CONVERT_TZ', $dt, $fromTz, $toTz);
    }

    // JSON functions

    /** `JSON_OBJECT(key, value, ...)`. */
    public static function jsonObject(Exp ...$keysValues): FuncExp
    {
        return self::call('JSON_OBJECT', ...$keysValues);
    }

    /** `JSON_ARRAY(...)`. */
    public static function jsonArray(Exp ...$values): FuncExp
    {
        return self::call('JSON_ARRAY', ...$values);
    }

    /** `JSON_QUOTE(str)`. */
    public static function jsonQuote(Exp $str): FuncExp
    {
        return self::call('JSON_QUOTE', $str);
    }

    /** `JSON_UNQUOTE(jsonVal)`. */
    public static function jsonUnquote(Exp $jsonVal): FuncExp
    {
        return self::call('JSON_UNQUOTE', $jsonVal);
    }

    /** `JSON_EXTRACT(doc, path, ...)`. */
    public static function jsonExtract(Exp $doc, Exp $path, Exp ...$rest): FuncExp
    {
        return self::call('JSON_EXTRACT', $doc, $path, ...$rest);
    }

    /** `JSON_CONTAINS(target, candidate)` or `JSON_CONTAINS(target, candidate, path)`. */
    public static function jsonContains(Exp $target, Exp $candidate, ?Exp $path = null): FuncExp
    {
        return $path === null
            ? self::call('JSON_CONTAINS', $target, $candidate)
            : self::call('JSON_CONTAINS', $target, $candidate, $path);
    }

    /** `JSON_CONTAINS_PATH(doc, oneOrAll, path, ...)`. */
    public static function jsonContainsPath(Exp $doc, Exp $oneOrAll, Exp $path, Exp ...$rest): FuncExp
    {
        return self::call('JSON_CONTAINS_PATH', $doc, $oneOrAll, $path, ...$rest);
    }

    /** `JSON_KEYS(doc)` or `JSON_KEYS(doc, path)`. */
    public static function jsonKeys(Exp $doc, Exp ...$path): FuncExp
    {
        return self::call('JSON_KEYS', $doc, ...$path);
    }

    /** `JSON_SEARCH(doc, oneOrAll, searchStr, ...)`. */
    public static function jsonSearch(Exp $doc, Exp $oneOrAll, Exp $searchStr, Exp ...$rest): FuncExp
    {
        return self::call('JSON_SEARCH', $doc, $oneOrAll, $searchStr, ...$rest);
    }

    /** `JSON_VALUE(doc, path)`. */
    public static function jsonValue(Exp $doc, Exp $path): FuncExp
    {
        return self::call('JSON_VALUE', $doc, $path);
    }

    /** `JSON_SET(doc, path, value, ...)`. */
    public static function jsonSet(Exp $doc, Exp $path, Exp $value, Exp ...$rest): FuncExp
    {
        return self::call('JSON_SET', $doc, $path, $value, ...$rest);
    }

    /** `JSON_INSERT(doc, path, value, ...)`. */
    public static function jsonInsert(Exp $doc, Exp $path, Exp $value, Exp ...$rest): FuncExp
    {
        return self::call('JSON_INSERT', $doc, $path, $value, ...$rest);
    }

    /** `JSON_REPLACE(doc, path, value, ...)`. */
    public static function jsonReplace(Exp $doc, Exp $path, Exp $value, Exp ...$rest): FuncExp
    {
        return self::call('JSON_REPLACE', $doc, $path, $value, ...$rest);
    }

    /** `JSON_REMOVE(doc, path, ...)`. */
    public static function jsonRemove(Exp $doc, Exp $path, Exp ...$rest): FuncExp
    {
        return self::call('JSON_REMOVE', $doc, $path, ...$rest);
    }

    /** `JSON_ARRAY_APPEND(doc, path, value, ...)`. */
    public static function jsonArrayAppend(Exp $doc, Exp $path, Exp $value, Exp ...$rest): FuncExp
    {
        return self::call('JSON_ARRAY_APPEND', $doc, $path, $value, ...$rest);
    }

    /** `JSON_ARRAY_INSERT(doc, path, value, ...)`. */
    public static function jsonArrayInsert(Exp $doc, Exp $path, Exp $value, Exp ...$rest): FuncExp
    {
        return self::call('JSON_ARRAY_INSERT', $doc, $path, $value, ...$rest);
    }

    /** `JSON_MERGE_PATCH(doc, doc, ...)` — RFC 7386 merge. */
    public static function jsonMergePatch(Exp $doc, Exp $other, Exp ...$rest): FuncExp
    {
        return self::call('JSON_MERGE_PATCH', $doc, $other, ...$rest);
    }

    /** `JSON_MERGE_PRESERVE(doc, doc, ...)` — merge keeping duplicate keys. */
    public static function jsonMergePreserve(Exp $doc, Exp $other, Exp ...$rest): FuncExp
    {
        return self::call('JSON_MERGE_PRESERVE', $doc, $other, ...$rest);
    }

    /** `JSON_TYPE(jsonVal)`. */
    public static function jsonType(Exp $jsonVal): FuncExp
    {
        return self::call('JSON_TYPE', $jsonVal);
    }

    /** `JSON_DEPTH(doc)`. */
    public static function jsonDepth(Exp $doc): FuncExp
    {
        return self::call('JSON_DEPTH', $doc);
    }

    /** `JSON_LENGTH(doc)` or `JSON_LENGTH(doc, path)`. */
    public static function jsonLength(Exp $doc, Exp ...$path): FuncExp
    {
        return self::call('JSON_LENGTH', $doc, ...$path);
    }

    /** `JSON_VALID(val)`. */
    public static function jsonValid(Exp $val): FuncExp
    {
        return self::call('JSON_VALID', $val);
    }

    // Miscellaneous functions

    /** `UUID()` — a version-1 UUID string. */
    public static function uuid(): FuncExp
    {
        return self::call('UUID');
    }

    /** `UUID_TO_BIN(uuid)` or `UUID_TO_BIN(uuid, swapFlag)`. */
    public static function uuidToBin(Exp $uuid, Exp ...$swapFlag): FuncExp
    {
        return self::call('UUID_TO_BIN', $uuid, ...$swapFlag);
    }

    /** `BIN_TO_UUID(bin)` or `BIN_TO_UUID(bin, swapFlag)`. */
    public static function binToUuid(Exp $bin, Exp ...$swapFlag): FuncExp
    {
        return self::call('BIN_TO_UUID', $bin, ...$swapFlag);
    }

    /** `IS_UUID(str)`. */
    public static function isUuid(Exp $str): FuncExp
    {
        return self::call('IS_UUID', $str);
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
        // The default is only meaningful with an offset, so it is dropped without one.
        $args = [$expr];
        if ($offset !== null) {
            $args[] = $offset;
            if ($default !== null) {
                $args[] = $default;
            }
        }

        return new WindowFuncBuilder(new FuncExp('LAG', $args));
    }

    /**
     * The `LEAD(expr[, offset[, default]])` window function: the value `offset` rows
     * after the current row within the partition.
     */
    public static function lead(Exp $expr, ?Exp $offset = null, ?Exp $default = null): WindowFuncBuilder
    {
        $args = [$expr];
        if ($offset !== null) {
            $args[] = $offset;
            if ($default !== null) {
                $args[] = $default;
            }
        }

        return new WindowFuncBuilder(new FuncExp('LEAD', $args));
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

    private static function call(string $name, Exp ...$args): FuncExp
    {
        return new FuncExp($name, array_values($args));
    }

    private static function agg(string $name, Exp ...$args): AggBuilder
    {
        return new AggBuilder($name, array_values($args));
    }

    /**
     * A function available only on the given dialect; validating against another
     * target reports it as unsupported.
     */
    private static function gated(Dialect $dialect, string $name, Exp ...$args): FuncExp
    {
        return new FuncExp($name, array_values($args), new Requirement($dialect));
    }

    /**
     * An aggregate available only on the given dialect.
     */
    private static function gatedAgg(Dialect $dialect, string $name, Exp ...$args): AggBuilder
    {
        return new AggBuilder($name, array_values($args), requires: new Requirement($dialect));
    }
}
