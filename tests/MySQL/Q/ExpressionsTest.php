<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL expressions', function () {
    it('renders identifiers, backtick-quoting reserved keywords', function () {
        expect(Q::n('users'))->toRenderSql('users');
        expect(Q::n('order'))->toRenderSql('`order`');
        expect(Q::n('u.name'))->toRenderSql('u.name');
    });

    it('quotes string literals with doubled quotes and escaped backslashes', function () {
        expect(Q::string("a'b"))->toRenderSql("'a''b'");
        expect(Q::string('a\\b'))->toRenderSql("'a\\\\b'");
    });

    it('renders numeric, bool and null literals', function () {
        expect(Q::int(10))->toRenderSql('10');
        expect(Q::float(0.5))->toRenderSql('0.5');
        expect(Q::bool(true))->toRenderSql('TRUE');
        expect(Q::null())->toRenderSql('NULL');
    });

    it('binds positional arguments as ?', function () {
        expect(Q::arg(5))->toRenderSql('?', [5]);
        expect(Q::n('a')->eq(Q::arg(1)))->toRenderSql('a = ?', [1]);
    });

    it('builds functions and CAST through the facade', function () {
        expect(Q::func('CONCAT', Q::n('a'), Q::string('x')))->toRenderSql("CONCAT(a, 'x')");
        expect(Q::func('POW', Q::n('a'), Q::int(2)))->toRenderSql('POW(a, 2)');
        expect(Q::cast(Q::n('a'), 'UNSIGNED'))->toRenderSql('CAST(a AS UNSIGNED)');
        expect(Q::cast(Q::n('a'), 'DECIMAL(10,2)'))->toRenderSql('CAST(a AS DECIMAL(10,2))');
        expect(Q::func('JSON_CONTAINS', Q::n('a'), Q::arg('1')))->toRenderSql('JSON_CONTAINS(a, ?)', ['1']);
    });

    it('renders null-safe equality', function () {
        expect(Q::n('a')->nullSafeEq(Q::arg(1)))->toRenderSql('a <=> ?', [1]);
    });

    it('renders JSON path extraction operators', function () {
        expect(Q::n('doc')->jsonExtract(Q::string('$.name')))->toRenderSql("doc -> '$.name'");
        expect(Q::n('doc')->jsonExtractText(Q::string('$.name')))->toRenderSql("doc ->> '$.name'");
    });

    it('renders bitwise operators', function () {
        expect(Q::n('flags')->bitAnd(Q::int(4)))->toRenderSql('flags & 4');
        expect(Q::n('flags')->bitOr(Q::int(1)))->toRenderSql('flags | 1');
        expect(Q::n('a')->bitXor(Q::n('b')))->toRenderSql('a ^ b');
        expect(Q::n('x')->shiftLeft(Q::int(2)))->toRenderSql('x << 2');
        expect(Q::n('x')->shiftRight(Q::int(2)))->toRenderSql('x >> 2');
        expect(Q::bitNot(Q::n('flags')))->toRenderSql('~ flags');
    });

    it('parenthesizes bitwise operators by precedence', function () {
        // & binds looser than +, so a + 1 needs no parentheses under &
        expect(Q::n('a')->plus(Q::int(1))->bitAnd(Q::int(4)))->toRenderSql('a + 1 & 4');
        // | binds looser than *, so forcing the OR first parenthesizes it
        expect(Q::n('a')->bitOr(Q::n('b'))->mult(Q::int(2)))->toRenderSql('(a | b) * 2');
        // ^ binds tighter than *, so no parentheses are needed
        expect(Q::n('a')->bitXor(Q::n('b'))->mult(Q::int(2)))->toRenderSql('a ^ b * 2');
    });

    it('renders IN with an argument list', function () {
        expect(Q::n('a')->in(Q::args(1, 2, 3)))->toRenderSql('a IN (?,?,?)', [1, 2, 3]);
        expect(Q::n('a')->notIn(Q::exps(Q::int(1), Q::int(2))))->toRenderSql('a NOT IN (1,2)');
    });

    it('renders REGEXP and LIKE', function () {
        expect(Q::n('a')->like(Q::string('%x%')))->toRenderSql("a LIKE '%x%'");
        expect(Q::n('a')->regexp(Q::string('^x')))->toRenderSql("a REGEXP '^x'");
    });

    it('combines conditions with AND / OR / NOT', function () {
        expect(Q::and(Q::n('a')->eq(Q::int(1)), Q::n('b')->eq(Q::int(2))))
            ->toRenderSql('a = 1 AND b = 2');
        expect(Q::not(Q::n('a')))->toRenderSql('NOT a');
        expect(Q::coalesce(Q::n('a'), Q::int(0)))->toRenderSql('COALESCE(a, 0)');
    });
});
