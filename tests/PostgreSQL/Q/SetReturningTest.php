<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('SetReturningFunctions', function () {
    it('renders generate_series with two arguments', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q\Func::generateSeries(Q::int(2), Q::int(4)));

        expect($q)->toRenderSql('SELECT * FROM generate_series(2, 4)', null);
    });

    it('renders generate_series with three arguments', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q\Func::generateSeries(Q::int(5), Q::int(1), Q::int(-2)));

        expect($q)->toRenderSql('SELECT * FROM generate_series(5, 1, -2)', null);
    });

    it('renders generate_series with alias', function () {
        $q = Q::select(Q::func('current_date')->plus(Q::n('s.a')))
            ->from(Q\Func::generateSeries(Q::int(0), Q::int(14), Q::int(7)))->as('s')->columnAliases('a');

        expect($q)->toRenderSql('SELECT current_date() + s.a FROM generate_series(0, 14, 7) AS s (a)', null);
    });

    it('renders generate_series with timestamp', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q\Func::generateSeries(
                Q::arg('2008-03-01 00:00')->cast('timestamp'),
                Q::string('2008-03-04 12:00'),
                Q::string('10 hours'),
            ));

        expect($q)->toRenderSql(
            "SELECT * FROM generate_series(\$1::timestamp, '2008-03-04 12:00', '10 hours')",
            ['2008-03-01 00:00'],
        );
    });

    it('renders generate_subscripts basic', function () {
        $q = Q::select(Q\Func::generateSubscripts(
            Q::arg('{NULL,1,NULL,2}')->cast('int[]'),
            Q::int(1),
        )->as('s'));

        expect($q)->toRenderSql('SELECT generate_subscripts($1::int[], 1) AS s', ['{NULL,1,NULL,2}']);
    });

    it('renders generate_subscripts in FROM clause', function () {
        $q = Q::select(Q::n('a'))->as('array')
            ->select(Q::n('s'))->as('subscript')
            ->select(Q::n('a')->subscript(Q::n('s')))->as('value')
            ->from(
                Q::select(
                    Q\Func::generateSubscripts(Q::n('a'), Q::int(1))->as('s'),
                    Q::n('a'),
                )->from(Q::n('arrays')),
            )->as('foo');

        expect($q)->toRenderSql(
            <<<'SQL'
            SELECT a AS array, s AS subscript, a[s] AS value
            FROM (SELECT generate_subscripts(a, 1) AS s, a FROM arrays) AS foo
            SQL,
            null,
        );
    });

    it('renders generate_subscripts with reverse', function () {
        $q = Q::select(Q::n('*'))
            ->from(Q\Func::generateSubscripts(
                Q::n('some_array'),
                Q::int(1),
                Q::bool(true),
            ));

        expect($q)->toRenderSql('SELECT * FROM generate_subscripts(some_array, 1, true)', null);
    });

    it('renders multiple generate_subscripts in FROM', function () {
        $q = Q::select(
            Q::n('i'),
            Q::n('j'),
        )->from(
            Q\Func::generateSubscripts(Q::n('some_array'), Q::int(1)),
        )->as('g1')->columnAliases('i')
            ->from(Q\Func::generateSubscripts(Q::n('some_array'), Q::int(2)))->as('g2')->columnAliases('j');

        expect($q)->toRenderSql(
            'SELECT i, j FROM generate_subscripts(some_array, 1) AS g1 (i), generate_subscripts(some_array, 2) AS g2 (j)',
            null,
        );
    });
});
