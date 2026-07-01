<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Op', function () {
    describe('cast', function () {
        it('single exp', function () {
            expect(Q::arg('foo')->cast('text'))->toRenderSql('$1::text', ['foo']);
        });

        it('combined exp', function () {
            expect(Q::n('json_column')->jsonExtractText(Q::arg('my_field'))->cast('int'))
                ->toRenderSql('(json_column ->> $1)::int', ['my_field']);
        });

        it('cast and like', function () {
            expect(Q::n('articles.content')->cast('text')->ilike(Q::arg('%foo%')))
                ->toRenderSql('articles.content::text ILIKE $1', ['%foo%']);
        });

        it('array type', function () {
            expect(Q::array(Q::arg('foo'), Q::arg('bar'))->cast('uuid[]'))
                ->toRenderSql('ARRAY[$1, $2]::uuid[]', ['foo', 'bar']);
        });
    });

    describe('precedence', function () {
        it('plus and mult', function () {
            expect(Q::n('a')->plus(Q::n('b'))->mult(Q::n('c')))->toRenderSql('(a + b) * c', null);
        });

        it('plus, plus and grouped minus', function () {
            expect(Q::n('a')->plus(Q::n('b'))->plus(Q::n('c')->minus(Q::n('d'))))->toRenderSql('a + b + (c - d)', null);
        });

        it('plus, plus and minus', function () {
            expect(Q::n('a')->plus(Q::n('b'))->plus(Q::n('c'))->minus(Q::n('d')))->toRenderSql('a + b + c - d', null);
        });

        it('plus, minus and plus', function () {
            expect(Q::n('a')->plus(Q::n('b'))->minus(Q::n('c')->plus(Q::n('d'))))->toRenderSql('a + b - (c + d)', null);
        });

        it('plus, plus and plus', function () {
            expect(Q::n('a')->plus(Q::n('b'))->plus(Q::n('c')->plus(Q::n('d'))))->toRenderSql('a + b + c + d', null);
        });

        it('plus times plus', function () {
            $e1 = Q::n('a')->plus(Q::n('b'));
            $e2 = Q::n('c')->plus(Q::n('d'));

            expect($e1->mult($e2))->toRenderSql('(a + b) * (c + d)', null);
        });
    });

    describe('subscript', function () {
        it('simple column subscript', function () {
            expect(Q::n('mytable.arraycolumn')->subscript(Q::int(4)))->toRenderSql('mytable.arraycolumn[4]', null);
        });

        it('parameter subscript', function () {
            expect(Q::arg(1)->subscript(Q::int(10)))->toRenderSql('$1[10]', [1]);
        });

        it('function call subscript with parentheses', function () {
            expect(Q::func('arrayfunction', Q::n('a'), Q::n('b'))->subscript(Q::int(42)))
                ->toRenderSql('(arrayfunction(a,b))[42]', null);
        });

        it('array slice with parameter', function () {
            expect(Q::arg(1)->subscript(Q::int(10), Q::int(42)))->toRenderSql('$1[10:42]', [1]);
        });

        it('column array slice', function () {
            expect(Q::n('mytable.arraycolumn')->subscript(Q::int(1), Q::int(5)))->toRenderSql('mytable.arraycolumn[1:5]', null);
        });

        it('multidimensional array subscript', function () {
            expect(Q::n('mytable.two_d_column')->subscript(Q::int(17))->subscript(Q::int(34)))
                ->toRenderSql('mytable.two_d_column[17][34]', null);
        });

        it('subscript with arithmetic expression', function () {
            expect(Q::n('a')->plus(Q::n('b'))->subscript(Q::int(1)))->toRenderSql('(a + b)[1]', null);
        });

        it('subscript without parentheses for high precedence', function () {
            expect(Q::n('a.b')->subscript(Q::int(1)))->toRenderSql('a.b[1]', null);
        });

        it('complex expression with subscript', function () {
            expect(Q::n('a')->mult(Q::n('b'))->subscript(Q::int(1), Q::int(3)))->toRenderSql('(a * b)[1:3]', null);
        });
    });

    describe('arithmetic operators', function () {
        it('divide, mod and pow', function () {
            expect(Q::n('a')->divide(Q::n('b')))->toRenderSql('a / b', null);
            expect(Q::n('a')->mod(Q::n('b')))->toRenderSql('a % b', null);
            expect(Q::n('a')->pow(Q::n('b')))->toRenderSql('a ^ b', null);
        });

        it('negates', function () {
            expect(Q::neg(Q::n('a')))->toRenderSql('- a', null);
        });
    });

    describe('json operators', function () {
        it('extracts a json value and path', function () {
            expect(Q::n('doc')->jsonExtract(Q::string('a')))->toRenderSql("doc -> 'a'", null);
            expect(Q::n('doc')->jsonExtractPath(Q::arg('{a,b}')))->toRenderSql('doc #> $1', ['{a,b}']);
            expect(Q::n('doc')->jsonExtractPathText(Q::arg('{a,b}')))->toRenderSql('doc #>> $1', ['{a,b}']);
        });

        it('tests containment', function () {
            expect(Q::n('doc')->contains(Q::arg('{"a":1}')))->toRenderSql('doc @> $1', ['{"a":1}']);
            expect(Q::n('doc')->containedBy(Q::arg('{"a":1}')))->toRenderSql('doc <@ $1', ['{"a":1}']);
        });
    });

    describe('comparison operators', function () {
        it('is distinct from', function () {
            expect(Q::n('a')->plus(Q::int(1))->isDistinctFrom(Q::n('b')))->toRenderSql('a + 1 IS DISTINCT FROM b', null);
        });

        it('is not distinct from', function () {
            expect(Q::not(Q::n('a'))->isNotDistinctFrom(Q::n('b')))->toRenderSql('(NOT a) IS NOT DISTINCT FROM b', null);
        });

        it('lte and gte', function () {
            expect(Q::n('a')->lte(Q::int(1)))->toRenderSql('a <= 1', null);
            expect(Q::n('a')->gte(Q::int(1)))->toRenderSql('a >= 1', null);
        });
    });
});
