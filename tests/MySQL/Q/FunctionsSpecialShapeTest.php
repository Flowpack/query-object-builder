<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL special-shape functions', function () {
    describe('GROUP_CONCAT', function () {
        it('renders a plain group concat', function () {
            expect(Q\Func::groupConcat(Q::n('name')))->toRenderSql('GROUP_CONCAT(name)');
        });

        it('renders DISTINCT, ORDER BY and SEPARATOR', function () {
            expect(
                Q\Func::groupConcat(Q::n('name'))
                    ->distinct()
                    ->orderBy(Q::n('name'))->desc()
                    ->separator(', '),
            )->toRenderSql("GROUP_CONCAT(DISTINCT name ORDER BY name DESC SEPARATOR ', ')");
        });

        it('orders ascending', function () {
            expect(
                Q\Func::groupConcat(Q::n('name'))->orderBy(Q::n('name'))->asc(),
            )->toRenderSql('GROUP_CONCAT(name ORDER BY name ASC)');
        });
    });

    describe('EXTRACT', function () {
        it('extracts a field from a date', function () {
            expect(Q\Func::extract('YEAR', Q::n('created')))->toRenderSql('EXTRACT(YEAR FROM created)');
        });
    });

    describe('INTERVAL and date arithmetic', function () {
        it('renders DATE_ADD with an interval', function () {
            expect(
                Q\Func::dateAdd(Q::n('created'), Q::interval(Q::int(1), 'DAY')),
            )->toRenderSql('DATE_ADD(created, INTERVAL 1 DAY)');
        });

        it('renders DATE_SUB with an interval', function () {
            expect(
                Q\Func::dateSub(Q::n('created'), Q::interval(Q::int(2), 'MONTH')),
            )->toRenderSql('DATE_SUB(created, INTERVAL 2 MONTH)');
        });

        it('uses an interval as an arithmetic operand', function () {
            expect(
                Q::n('created')->plus(Q::interval(Q::int(7), 'DAY')),
            )->toRenderSql('created + INTERVAL 7 DAY');
        });
    });

    describe('TRIM', function () {
        it('renders a plain trim', function () {
            expect(Q\Func::trim(Q::n('name')))->toRenderSql('TRIM(name)');
        });

        it('renders directional trims with a remove string', function () {
            expect(Q\Func::trimLeading(Q::string('x'), Q::n('name')))
                ->toRenderSql("TRIM(LEADING 'x' FROM name)");
            expect(Q\Func::trimTrailing(Q::string('x'), Q::n('name')))
                ->toRenderSql("TRIM(TRAILING 'x' FROM name)");
            expect(Q\Func::trimBoth(Q::string('x'), Q::n('name')))
                ->toRenderSql("TRIM(BOTH 'x' FROM name)");
        });
    });

    describe('conditional functions', function () {
        it('renders NULLIF, GREATEST and LEAST', function () {
            expect(Q::nullif(Q::n('a'), Q::int(0)))->toRenderSql('NULLIF(a, 0)');
            expect(Q::greatest(Q::n('a'), Q::n('b'), Q::int(0)))->toRenderSql('GREATEST(a, b, 0)');
            expect(Q::least(Q::n('a'), Q::n('b')))->toRenderSql('LEAST(a, b)');
        });

        it('renders IF and IFNULL', function () {
            expect(Q\Func::if(Q::n('a')->gt(Q::int(0)), Q::int(1), Q::int(0)))
                ->toRenderSql('IF(a > 0, 1, 0)');
            expect(Q\Func::ifnull(Q::n('a'), Q::int(0)))->toRenderSql('IFNULL(a, 0)');
        });
    });

    describe('CONVERT', function () {
        it('renders the function-call cast form', function () {
            expect(Q::convert(Q::n('a'), 'DECIMAL(10,2)'))->toRenderSql('CONVERT(a, DECIMAL(10,2))');
        });
    });
});
