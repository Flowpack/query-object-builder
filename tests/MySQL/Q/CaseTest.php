<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL CASE', function () {
    it('renders a searched CASE expression', function () {
        $case = Q::case()
            ->when(Q::n('a')->eq(Q::int(1)))->then(Q::string('one'))
            ->when(Q::n('a')->eq(Q::int(2)))->then(Q::string('two'))
            ->else(Q::string('other'))
            ->end();

        expect($case)->toRenderSql("CASE WHEN a = 1 THEN 'one' WHEN a = 2 THEN 'two' ELSE 'other' END");
    });

    it('renders a simple CASE expression with a leading operand', function () {
        $case = Q::case(Q::n('status'))
            ->when(Q::int(1))->then(Q::string('active'))
            ->else(Q::string('inactive'))
            ->end();

        expect($case)->toRenderSql("CASE status WHEN 1 THEN 'active' ELSE 'inactive' END");
    });

    it('rejects a CASE with no conditions, unless validation is disabled', function () {
        $case = Q::case()->end();

        expect(static fn () => Q::build($case)->toSql())
            ->toThrow(QueryBuilderException::class, 'case: no conditions given');

        // The check is advisory: withoutValidation() still emits the (degenerate) SQL.
        [$sql] = Q::build($case)->withoutValidation()->toSql();
        expect($sql)->toBe('CASE END');
    });
});
