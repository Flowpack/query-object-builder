<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Datetime', function () {
    it('renders extract', function () {
        $b = Q\Func::extract('YEARS', Q::func('age', Q::arg('2023-03-30')->cast('date'), Q::n('u.birthday')));

        expect($b)->toRenderSql('EXTRACT(YEARS FROM age($1::date,u.birthday))', ['2023-03-30']);
    });
});
