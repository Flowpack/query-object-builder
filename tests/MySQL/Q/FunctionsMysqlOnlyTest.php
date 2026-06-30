<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL-only functions', function () {
    it('renders MySQL-only functions', function (Exp $exp, string $sql) {
        expect($exp)->toRenderSql($sql);
    })->with([
        'regexpLike' => [fn () => Q\Func::regexpLike(Q::n('a'), Q::string('^x')), "REGEXP_LIKE(a, '^x')"],
        'regexpLike matchType' => [fn () => Q\Func::regexpLike(Q::n('a'), Q::string('^x'), Q::string('i')), "REGEXP_LIKE(a, '^x', 'i')"],
        'grouping' => [fn () => Q\Func::grouping(Q::n('a'), Q::n('b')), 'GROUPING(a, b)'],
        'anyValue' => [fn () => Q\Func::anyValue(Q::n('name')), 'ANY_VALUE(name)'],
        'jsonSchemaValid' => [fn () => Q\Func::jsonSchemaValid(Q::n('schema'), Q::n('doc')), 'JSON_SCHEMA_VALID(`schema`, doc)'],
        'jsonSchemaValidationReport' => [fn () => Q\Func::jsonSchemaValidationReport(Q::n('s'), Q::n('doc')), 'JSON_SCHEMA_VALIDATION_REPORT(s, doc)'],
        'jsonStorageSize' => [fn () => Q\Func::jsonStorageSize(Q::n('doc')), 'JSON_STORAGE_SIZE(doc)'],
        'jsonStorageFree' => [fn () => Q\Func::jsonStorageFree(Q::n('doc')), 'JSON_STORAGE_FREE(doc)'],
        'jsonPretty' => [fn () => Q\Func::jsonPretty(Q::n('doc')), 'JSON_PRETTY(doc)'],
        'randomBytes' => [fn () => Q\Func::randomBytes(Q::int(16)), 'RANDOM_BYTES(16)'],
    ]);

    it('uses GROUPING with WITH ROLLUP', function () {
        expect(
            Q::select(Q::n('country'), Q\Func::sum(Q::n('amount')), Q\Func::grouping(Q::n('country')))
                ->from(Q::n('sales'))
                ->groupBy(Q::n('country'))->withRollup(),
        )->toRenderSql(
            'SELECT country, SUM(amount), GROUPING(country) FROM sales GROUP BY country WITH ROLLUP',
        );
    });
});
