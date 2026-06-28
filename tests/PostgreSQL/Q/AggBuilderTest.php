<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('AggBuilder', function () {
    it('renders a basic aggregate', function () {
        $b = Q::agg('my_agg', Q::n('foo'));

        expect($b)->toRenderSql('my_agg(foo)', null);
    });

    it('renders distinct, order by and filter', function () {
        $b = Q::agg('my_agg', Q::n('foo'), Q::n('bar'))
            ->distinct()
            ->orderBy(Q::n('foo'))->nullsFirst()->asc()
            ->orderBy(Q::n('bar'))->nullsLast()->desc()
            ->filter(Q::n('foo')->gt(Q::int(1)));

        expect($b)->toRenderSql(
            'my_agg(DISTINCT foo,bar ORDER BY foo ASC NULLS FIRST,bar DESC NULLS LAST) FILTER (WHERE foo > 1)',
            null,
        );
    });
});
