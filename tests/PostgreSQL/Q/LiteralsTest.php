<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('Array', function () {
    it('with ints', function () {
        expect(Q::array(Q::int(1), Q::int(2), Q::int(3)))->toRenderSql('ARRAY[1,2,3]', null);
    });

    it('with ident', function () {
        expect(Q::array(Q::int(1), Q::int(2), Q::n('bar')))->toRenderSql('ARRAY[1,2,bar]', null);
    });

    it('with placeholder', function () {
        expect(Q::array(Q::int(1), Q::int(2), Q::arg(3)))->toRenderSql('ARRAY[1,2,$1]', [3]);
    });
});

describe('String', function () {
    it('quotes string literals', function (string $input, string $expected) {
        expect(Q::string($input))->toRenderSql($expected, null);
    })->with([
        'plain' => ['foo', "'foo'"],
        'single quote' => ["foo'bar", "'foo''bar'"],
        'backslash escapes' => ['with some \n escapes', "E'with some \\\\n escapes'"],
    ]);
});
