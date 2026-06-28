<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\TypeExp;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('TypeExp', function () {
    it('renders valid types', function (string $type) {
        [$sql] = Q::build(new TypeExp($type))->toSql();

        expect($sql)->toBe(trim($type));
    })->with([
        'integer',
        'text',
        'boolean',
        'varchar(255)',
        'custom_type',
        '"QuotedType"',
        'integer[]',
        'text [ ] [ ]',
        '"QuotedArrayType"[]',
        'integer[][]',
        'text[16]',
    ]);

    it('rejects invalid types', function (string $type) {
        expect(static fn () => Q::build(new TypeExp($type))->toSql())->toThrow(QueryBuilderException::class);
    })->with([
        '1int',
        '"MyTable.name',
        'My"Table.name',
    ]);
});
