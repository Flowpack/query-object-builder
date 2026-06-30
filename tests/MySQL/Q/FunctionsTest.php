<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL curated function set', function () {
    it('renders scalar functions', function (Exp $exp, string $sql) {
        expect($exp)->toRenderSql($sql);
    })->with([
        // String
        'concat' => [fn () => Q\Func::concat(Q::n('a'), Q::string('-'), Q::n('b')), "CONCAT(a, '-', b)"],
        'concatWs' => [fn () => Q\Func::concatWs(Q::string(','), Q::n('a'), Q::n('b')), "CONCAT_WS(',', a, b)"],
        'lower' => [fn () => Q\Func::lower(Q::n('a')), 'LOWER(a)'],
        'upper' => [fn () => Q\Func::upper(Q::n('a')), 'UPPER(a)'],
        'length' => [fn () => Q\Func::length(Q::n('a')), 'LENGTH(a)'],
        'charLength' => [fn () => Q\Func::charLength(Q::n('a')), 'CHAR_LENGTH(a)'],
        'substring 2-arg' => [fn () => Q\Func::substring(Q::n('a'), Q::int(2)), 'SUBSTRING(a, 2)'],
        'substring 3-arg' => [fn () => Q\Func::substring(Q::n('a'), Q::int(2), Q::int(3)), 'SUBSTRING(a, 2, 3)'],
        'left' => [fn () => Q\Func::left(Q::n('a'), Q::int(3)), 'LEFT(a, 3)'],
        'right' => [fn () => Q\Func::right(Q::n('a'), Q::int(3)), 'RIGHT(a, 3)'],
        'ltrim' => [fn () => Q\Func::ltrim(Q::n('a')), 'LTRIM(a)'],
        'rtrim' => [fn () => Q\Func::rtrim(Q::n('a')), 'RTRIM(a)'],
        'lpad' => [fn () => Q\Func::lpad(Q::n('a'), Q::int(5), Q::string('0')), "LPAD(a, 5, '0')"],
        'rpad' => [fn () => Q\Func::rpad(Q::n('a'), Q::int(5), Q::string('0')), "RPAD(a, 5, '0')"],
        'replace' => [fn () => Q\Func::replace(Q::n('a'), Q::string('x'), Q::string('y')), "REPLACE(a, 'x', 'y')"],
        'repeat' => [fn () => Q\Func::repeat(Q::string('ab'), Q::int(3)), "REPEAT('ab', 3)"],
        'reverse' => [fn () => Q\Func::reverse(Q::n('a')), 'REVERSE(a)'],
        'locate' => [fn () => Q\Func::locate(Q::string('x'), Q::n('a')), "LOCATE('x', a)"],
        'instr' => [fn () => Q\Func::instr(Q::n('a'), Q::string('x')), "INSTR(a, 'x')"],
        'substringIndex' => [fn () => Q\Func::substringIndex(Q::n('a'), Q::string('.'), Q::int(2)), "SUBSTRING_INDEX(a, '.', 2)"],
        'field' => [fn () => Q\Func::field(Q::n('a'), Q::string('x'), Q::string('y')), "FIELD(a, 'x', 'y')"],
        'findInSet' => [fn () => Q\Func::findInSet(Q::string('b'), Q::n('tags')), "FIND_IN_SET('b', tags)"],
        'format' => [fn () => Q\Func::format(Q::n('n'), Q::int(2)), 'FORMAT(n, 2)'],
        'hex' => [fn () => Q\Func::hex(Q::n('a')), 'HEX(a)'],
        'unhex' => [fn () => Q\Func::unhex(Q::n('a')), 'UNHEX(a)'],

        // Regexp
        'regexpReplace' => [fn () => Q\Func::regexpReplace(Q::n('a'), Q::string('x'), Q::string('y')), "REGEXP_REPLACE(a, 'x', 'y')"],
        'regexpInstr' => [fn () => Q\Func::regexpInstr(Q::n('a'), Q::string('x')), "REGEXP_INSTR(a, 'x')"],
        'regexpSubstr' => [fn () => Q\Func::regexpSubstr(Q::n('a'), Q::string('x')), "REGEXP_SUBSTR(a, 'x')"],

        // Numeric
        'abs' => [fn () => Q\Func::abs(Q::n('n')), 'ABS(n)'],
        'ceil' => [fn () => Q\Func::ceil(Q::n('n')), 'CEIL(n)'],
        'floor' => [fn () => Q\Func::floor(Q::n('n')), 'FLOOR(n)'],
        'round 1-arg' => [fn () => Q\Func::round(Q::n('n')), 'ROUND(n)'],
        'round 2-arg' => [fn () => Q\Func::round(Q::n('n'), Q::int(2)), 'ROUND(n, 2)'],
        'truncate' => [fn () => Q\Func::truncate(Q::n('n'), Q::int(2)), 'TRUNCATE(n, 2)'],
        'mod' => [fn () => Q\Func::mod(Q::n('a'), Q::int(3)), 'MOD(a, 3)'],
        'power' => [fn () => Q\Func::power(Q::n('a'), Q::int(2)), 'POWER(a, 2)'],
        'sqrt' => [fn () => Q\Func::sqrt(Q::n('n')), 'SQRT(n)'],
        'exp' => [fn () => Q\Func::exp(Q::n('n')), 'EXP(n)'],
        'ln' => [fn () => Q\Func::ln(Q::n('n')), 'LN(n)'],
        'log 1-arg' => [fn () => Q\Func::log(Q::n('n')), 'LOG(n)'],
        'log 2-arg' => [fn () => Q\Func::log(Q::int(2), Q::n('n')), 'LOG(2, n)'],
        'log2' => [fn () => Q\Func::log2(Q::n('n')), 'LOG2(n)'],
        'log10' => [fn () => Q\Func::log10(Q::n('n')), 'LOG10(n)'],
        'sign' => [fn () => Q\Func::sign(Q::n('n')), 'SIGN(n)'],
        'rand' => [fn () => Q\Func::rand(), 'RAND()'],
        'rand seed' => [fn () => Q\Func::rand(Q::int(1)), 'RAND(1)'],
        'pi' => [fn () => Q\Func::pi(), 'PI()'],
        'sin' => [fn () => Q\Func::sin(Q::n('n')), 'SIN(n)'],
        'cos' => [fn () => Q\Func::cos(Q::n('n')), 'COS(n)'],
        'tan' => [fn () => Q\Func::tan(Q::n('n')), 'TAN(n)'],
        'atan2' => [fn () => Q\Func::atan2(Q::n('y'), Q::n('x')), 'ATAN2(y, x)'],
        'radians' => [fn () => Q\Func::radians(Q::n('d')), 'RADIANS(d)'],
        'degrees' => [fn () => Q\Func::degrees(Q::n('r')), 'DEGREES(r)'],

        // Date / time
        'now' => [fn () => Q\Func::now(), 'NOW()'],
        'curdate' => [fn () => Q\Func::curdate(), 'CURDATE()'],
        'currentTimestamp' => [fn () => Q\Func::currentTimestamp(), 'CURRENT_TIMESTAMP()'],
        'year' => [fn () => Q\Func::year(Q::n('d')), 'YEAR(d)'],
        'month' => [fn () => Q\Func::month(Q::n('d')), 'MONTH(d)'],
        'dayOfWeek' => [fn () => Q\Func::dayOfWeek(Q::n('d')), 'DAYOFWEEK(d)'],
        'week mode' => [fn () => Q\Func::week(Q::n('d'), Q::int(1)), 'WEEK(d, 1)'],
        'dateDiff' => [fn () => Q\Func::dateDiff(Q::n('a'), Q::n('b')), 'DATEDIFF(a, b)'],
        'timestampDiff' => [fn () => Q\Func::timestampDiff('DAY', Q::n('a'), Q::n('b')), 'TIMESTAMPDIFF(DAY, a, b)'],
        'timestampAdd' => [fn () => Q\Func::timestampAdd('HOUR', Q::int(2), Q::n('d')), 'TIMESTAMPADD(HOUR, 2, d)'],
        'dateFormat' => [fn () => Q\Func::dateFormat(Q::n('d'), Q::string('%Y')), "DATE_FORMAT(d, '%Y')"],
        'unixTimestamp' => [fn () => Q\Func::unixTimestamp(), 'UNIX_TIMESTAMP()'],
        'fromUnixtime' => [fn () => Q\Func::fromUnixtime(Q::n('ts')), 'FROM_UNIXTIME(ts)'],
        'convertTz' => [fn () => Q\Func::convertTz(Q::n('d'), Q::string('+00:00'), Q::string('+02:00')), "CONVERT_TZ(d, '+00:00', '+02:00')"],

        // JSON
        'jsonObject' => [fn () => Q\Func::jsonObject(Q::string('k'), Q::n('v')), "JSON_OBJECT('k', v)"],
        'jsonArray' => [fn () => Q\Func::jsonArray(Q::int(1), Q::int(2)), 'JSON_ARRAY(1, 2)'],
        'jsonExtract' => [fn () => Q\Func::jsonExtract(Q::n('doc'), Q::string('$.a')), "JSON_EXTRACT(doc, '$.a')"],
        'jsonContains 2-arg' => [fn () => Q\Func::jsonContains(Q::n('doc'), Q::string('1')), "JSON_CONTAINS(doc, '1')"],
        'jsonContains 3-arg' => [fn () => Q\Func::jsonContains(Q::n('doc'), Q::string('1'), Q::string('$.a')), "JSON_CONTAINS(doc, '1', '$.a')"],
        'jsonContainsPath' => [fn () => Q\Func::jsonContainsPath(Q::n('doc'), Q::string('one'), Q::string('$.a')), "JSON_CONTAINS_PATH(doc, 'one', '$.a')"],
        'jsonKeys' => [fn () => Q\Func::jsonKeys(Q::n('doc')), 'JSON_KEYS(doc)'],
        'jsonValue' => [fn () => Q\Func::jsonValue(Q::n('doc'), Q::string('$.a')), "JSON_VALUE(doc, '$.a')"],
        'jsonSet' => [fn () => Q\Func::jsonSet(Q::n('doc'), Q::string('$.a'), Q::int(1)), "JSON_SET(doc, '$.a', 1)"],
        'jsonRemove' => [fn () => Q\Func::jsonRemove(Q::n('doc'), Q::string('$.a')), "JSON_REMOVE(doc, '$.a')"],
        'jsonMergePatch' => [fn () => Q\Func::jsonMergePatch(Q::n('a'), Q::n('b')), 'JSON_MERGE_PATCH(a, b)'],
        'jsonType' => [fn () => Q\Func::jsonType(Q::n('v')), 'JSON_TYPE(v)'],
        'jsonLength' => [fn () => Q\Func::jsonLength(Q::n('doc')), 'JSON_LENGTH(doc)'],
        'jsonValid' => [fn () => Q\Func::jsonValid(Q::n('v')), 'JSON_VALID(v)'],

        // Misc
        'uuid' => [fn () => Q\Func::uuid(), 'UUID()'],
        'uuidToBin' => [fn () => Q\Func::uuidToBin(Q::n('u')), 'UUID_TO_BIN(u)'],
        'isUuid' => [fn () => Q\Func::isUuid(Q::n('u')), 'IS_UUID(u)'],

        // Aggregates
        'jsonArrayAgg' => [fn () => Q\Func::jsonArrayAgg(Q::n('v')), 'JSON_ARRAYAGG(v)'],
        'jsonObjectAgg' => [fn () => Q\Func::jsonObjectAgg(Q::n('k'), Q::n('v')), 'JSON_OBJECTAGG(k, v)'],
        'bitOr' => [fn () => Q\Func::bitOr(Q::n('flags')), 'BIT_OR(flags)'],
        'stddevPop' => [fn () => Q\Func::stddevPop(Q::n('v')), 'STDDEV_POP(v)'],
        'varSamp' => [fn () => Q\Func::varSamp(Q::n('v')), 'VAR_SAMP(v)'],
    ]);

    it('uses an aggregate with DISTINCT and as a window function', function () {
        expect(Q\Func::count(Q::n('id'))->distinct())->toRenderSql('COUNT(DISTINCT id)');
        expect(Q\Func::sum(Q::n('v'))->over()->partitionBy(Q::n('g')))
            ->toRenderSql('SUM(v) OVER (PARTITION BY g)');
    });
});
