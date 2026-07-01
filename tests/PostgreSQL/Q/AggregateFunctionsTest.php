<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\Exp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('AggregateExpressions', function () {
    it('renders example 1.1: array_agg with order by', function () {
        $q = Q::select(Q\Func::arrayAgg(Q::n('a'))->orderBy(Q::n('b'))->desc())->from(Q::n('table'));

        expect($q)->toRenderSql('SELECT array_agg(a ORDER BY b DESC) FROM "table"', null);
    });

    it('renders example 1.2: string_agg with order by', function () {
        $q = Q::select(Q\Func::stringAgg(Q::n('a'), Q::string(','))->orderBy(Q::n('a')))->from(Q::n('table'));

        expect($q)->toRenderSql("SELECT string_agg(a,',' ORDER BY a) FROM \"table\"", null);
    });

    it('renders example 1.3: percentile_cont within group', function () {
        $q = Q::select(Q\Func::percentileCont(Q::float(0.5))->withinGroup()->orderBy(Q::n('income')))->from(Q::n('households'));

        expect($q)->toRenderSql('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY income) FROM households', null);
    });

    it('renders example 2.1: grouping with rollup', function () {
        $q = Q::select(Q::n('make'), Q::n('model'), Q\Func::grouping(Q::n('make'), Q::n('model')), Q\Func::sum(Q::n('sales')))
            ->from(Q::n('items_sold'))
            ->groupBy()->rollup(Q::exps(Q::n('make'), Q::n('model')));

        expect($q)->toRenderSql('SELECT make, model, GROUPING(make,model), sum(sales) FROM items_sold GROUP BY ROLLUP (make,model)', null);
    });

    it('renders example 3.1: distinct string_agg with quoted identifiers, joins and having', function () {
        $q = Q::select(Q::n('"Title"'))->as('"Album"')
            ->select(Q\Func::stringAgg(Q::n('"Genre"."Name"'), Q::string(','))->distinct()->orderBy(Q::n('"Genre"."Name"')))->as('"Genres"')
            ->from(Q::n('"Track"'))
            ->join(Q::n('"Genre"'))->using('"GenreId"')
            ->join(Q::n('"Album"'))->using('"AlbumId"')
            ->groupBy(Q::n('"Title"'))
            ->having(Q\Func::count(Q::n('"Genre"."Name"'))->distinct()->gt(Q::int(1)));

        expect($q)->toRenderSql(
            <<<'SQL'
            SELECT "Title" AS "Album",
                   string_agg(
                           DISTINCT "Genre"."Name", ','
                           ORDER BY "Genre"."Name"
                       )
                         AS "Genres"
            FROM "Track"
                     JOIN "Genre" USING ("GenreId")
                     JOIN "Album" USING ("AlbumId")
            GROUP BY "Title"
            HAVING count(DISTINCT "Genre"."Name") > 1
            SQL,
            null,
        );
    });

    it('renders aggregate function', function (Exp $fn, string $expected) {
        expect($fn)->toRenderSql($expected, null);
    })->with([
        'array_agg' => fn () => [Q\Func::arrayAgg(Q::n('title'))->orderBy(Q::n('title'))->desc(), 'array_agg(title ORDER BY title DESC)'],
        'avg' => fn () => [Q\Func::avg(Q::n('price')), 'avg(price)'],
        'bit_and' => fn () => [Q\Func::bitAnd(Q::n('flags')), 'bit_and(flags)'],
        'bit_or' => fn () => [Q\Func::bitOr(Q::n('flags')), 'bit_or(flags)'],
        'bit_xor' => fn () => [Q\Func::bitXor(Q::n('flags')), 'bit_xor(flags)'],
        'bool_and' => fn () => [Q\Func::boolAnd(Q::n('active')), 'bool_and(active)'],
        'bool_or' => fn () => [Q\Func::boolOr(Q::n('active')), 'bool_or(active)'],
        'count' => fn () => [Q\Func::count(Q::n('*')), 'count(*)'],
        'json_agg' => fn () => [Q\Func::jsonAgg(Q::n('title')), 'json_agg(title)'],
        'jsonb_agg' => fn () => [Q\Func::jsonbAgg(Q::n('title')), 'jsonb_agg(title)'],
        'json_object_agg' => fn () => [Q\Func::jsonObjectAgg(Q::n('title'), Q::n('price')), 'json_object_agg(title, price)'],
        'jsonb_object_agg' => fn () => [Q\Func::jsonbObjectAgg(Q::n('title'), Q::n('price')), 'jsonb_object_agg(title, price)'],
        'string_agg' => fn () => [Q\Func::stringAgg(Q::n('title'), Q::string(','))->orderBy(Q::n('title'))->desc(), "string_agg(title, ',' ORDER BY title DESC)"],
        'max' => fn () => [Q\Func::max(Q::n('price')), 'max(price)'],
        'min' => fn () => [Q\Func::min(Q::n('price')), 'min(price)'],
        'range_agg' => fn () => [Q\Func::rangeAgg(Q::n('price')), 'range_agg(price)'],
        'range_intersect_agg' => fn () => [Q\Func::rangeIntersectAgg(Q::n('price')), 'range_intersect_agg(price)'],
        'sum' => fn () => [Q\Func::sum(Q::n('price')), 'sum(price)'],
        'xmlagg' => fn () => [Q\Func::xmlagg(Q::n('title')), 'xmlagg(title)'],
        'mode' => fn () => [Q\Func::mode()->withinGroup()->orderBy(Q::n('price'))->asc(), 'mode() WITHIN GROUP (ORDER BY price ASC)'],
        'percentile_cont' => fn () => [Q\Func::percentileCont(Q::float(0.5))->withinGroup()->orderBy(Q::n('price'))->asc(), 'percentile_cont(0.5) WITHIN GROUP (ORDER BY price ASC)'],
        'percentile_disc' => fn () => [Q\Func::percentileDisc(Q::float(0.5))->withinGroup()->orderBy(Q::n('price'))->asc(), 'percentile_disc(0.5) WITHIN GROUP (ORDER BY price ASC)'],
        'rank' => fn () => [Q\Func::rank()->withinGroup()->orderBy(Q::n('price'))->asc(), 'rank() WITHIN GROUP (ORDER BY price ASC)'],
        'dense_rank' => fn () => [Q\Func::denseRank()->withinGroup()->orderBy(Q::n('price'))->asc(), 'dense_rank() WITHIN GROUP (ORDER BY price ASC)'],
        'percent_rank' => fn () => [Q\Func::percentRank()->withinGroup()->orderBy(Q::n('price'))->asc(), 'percent_rank() WITHIN GROUP (ORDER BY price ASC)'],
        'cume_dist' => fn () => [Q\Func::cumeDist()->withinGroup()->orderBy(Q::n('price'))->asc(), 'cume_dist() WITHIN GROUP (ORDER BY price ASC)'],
        'grouping' => fn () => [Q\Func::grouping(Q::n('price'), Q::n('title')), 'GROUPING(price,title)'],
    ]);
});
