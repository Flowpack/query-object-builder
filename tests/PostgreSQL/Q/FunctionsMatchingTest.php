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

    it('negated like and similar to', function () {
        expect(Q::n('name')->notLike(Q::string('foo%')))->toRenderSql("name NOT LIKE 'foo%'", null);
        expect(Q::n('name')->notILike(Q::string('foo%')))->toRenderSql("name NOT ILIKE 'foo%'", null);
        expect(Q::n('name')->notSimilarTo(Q::string('%(b|d)%')))->toRenderSql("name NOT SIMILAR TO '%(b|d)%'", null);
    });

    it('ilike', function () {
        expect(Q::n('name')->ilike(Q::string('foo%')))->toRenderSql("name ILIKE 'foo%'", null);
    });

    it('POSIX regular-expression operators', function () {
        expect(Q::n('name')->regexpMatch(Q::string('^foo')))->toRenderSql("name ~ '^foo'", null);
        expect(Q::n('name')->regexpIMatch(Q::string('^foo')))->toRenderSql("name ~* '^foo'", null);
        expect(Q::n('name')->regexpNotMatch(Q::string('^foo')))->toRenderSql("name !~ '^foo'", null);
        expect(Q::n('name')->regexpINotMatch(Q::string('^foo')))->toRenderSql("name !~* '^foo'", null);
    });
});
