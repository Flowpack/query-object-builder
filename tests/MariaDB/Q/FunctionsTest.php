<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MariaDB\Q;

describe('MariaDB functions', function () {
    it('exposes the shared function set', function () {
        expect(Q\Func::lower(Q::n('a')))->toRenderSql('LOWER(a)');
        expect(Q\Func::count(Q::n('*')))->toRenderSql('COUNT(*)');
        expect(Q\Func::jsonExtract(Q::n('doc'), Q::string('$.a')))->toRenderSql("JSON_EXTRACT(doc, '$.a')");
        expect(Q\Func::rowNumber()->over()->orderBy(Q::n('x')))->toRenderSql('ROW_NUMBER() OVER (ORDER BY x)');
        expect(Q\Func::groupConcat(Q::n('name'))->separator(', '))->toRenderSql("GROUP_CONCAT(name SEPARATOR ', ')");
    });

    it('renders MariaDB-only functions', function () {
        expect(Q\Func::jsonQuery(Q::n('doc'), Q::string('$.a')))->toRenderSql("JSON_QUERY(doc, '$.a')");
        expect(Q\Func::jsonExists(Q::n('doc'), Q::string('$.a')))->toRenderSql("JSON_EXISTS(doc, '$.a')");
        expect(Q\Func::jsonDetailed(Q::n('doc')))->toRenderSql('JSON_DETAILED(doc)');
        expect(Q\Func::median(Q::n('v'))->over()->partitionBy(Q::n('g')))
            ->toRenderSql('MEDIAN(v) OVER (PARTITION BY g)');
        expect(Q\Func::toChar(Q::n('d'), Q::string('YYYY-MM-DD')))->toRenderSql("TO_CHAR(d, 'YYYY-MM-DD')");
        expect(Q\Func::addMonths(Q::n('d'), Q::int(3)))->toRenderSql('ADD_MONTHS(d, 3)');
        expect(Q\Func::monthsBetween(Q::n('a'), Q::n('b')))->toRenderSql('MONTHS_BETWEEN(a, b)');
        expect(Q\Func::chr(Q::int(65)))->toRenderSql('CHR(65)');
        expect(Q\Func::oct(Q::int(8)))->toRenderSql('OCT(8)');
    });

    // MySQL-only functions (regexpLike / jsonPretty / randomBytes / ...) are absent
    // from this facade by construction — calling them is a compile-time type error,
    // which is the intended guarantee, so there is nothing to assert at runtime.
});
