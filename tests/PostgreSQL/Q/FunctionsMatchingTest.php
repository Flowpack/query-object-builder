<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Matching', function () {
    it('like', function () {
        expect(Q::n('name')->like(Q::string('foo%')))->toRenderSql("name LIKE 'foo%'", null);
    });

    it('similar to', function () {
        expect(Q::n('name')->similarTo(Q::string('%(b|d)%')))->toRenderSql("name SIMILAR TO '%(b|d)%'", null);
    });
});
