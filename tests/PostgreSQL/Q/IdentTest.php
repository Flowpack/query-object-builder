<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('N (identifier)', function () {
    it('renders and auto-quotes identifiers', function (string $input, string $expected) {
        [$sql] = Q::build(Q::n($input))->toSql();

        expect($sql)->toBe($expected);
    })->with([
        // Regular identifiers (unchanged)
        ['column_name1', 'column_name1'],
        ['users', 'users'],
        ['táblá_ñámé', 'táblá_ñámé'],
        ['öäüß_column', 'öäüß_column'],
        ['space_trimmed ', 'space_trimmed'],

        // Dotted paths without keywords (unchanged)
        ['public.users', 'public.users'],
        ['schema.mytable.mycolumn', 'schema.mytable.mycolumn'],

        // Asterisks (unchanged)
        ['*', '*'],
        ['mytable.*', 'mytable.*'],
        ['public.mytable.*', 'public.mytable.*'],

        // Dotted paths with "table" and "column" keywords
        ['schema.table.column', 'schema."table"."column"'],
        ['table.*', '"table".*'],
        ['public.table.*', 'public."table".*'],

        // Already quoted identifiers (unchanged)
        ['"MyTable".name', '"MyTable".name'],
        ['public."MyTable".*', 'public."MyTable".*'],
        ['"My"."Table".name', '"My"."Table".name'],
        ['"My""Quoted""Table".*', '"My""Quoted""Table".*'],

        // Unicode identifiers (unchanged)
        ['U&"d\0061t\+000061"', 'U&"d\0061t\+000061"'],
        ['U&"\0441\043B\043E\043D"', 'U&"\0441\043B\043E\043D"'],
        ['U&"d!0061t!+000061" UESCAPE \'!\'', 'U&"d!0061t!+000061" UESCAPE \'!\''],

        // Keywords - should be auto-quoted
        ['from', '"from"'],
        ['select', '"select"'],
        ['where', '"where"'],
        ['order', '"order"'],
        ['group', '"group"'],
        ['user', '"user"'],
        ['table', '"table"'],
        ['to', '"to"'],
        ['all', '"all"'],
        ['and', '"and"'],
        ['or', '"or"'],
        ['not', '"not"'],
        ['null', '"null"'],
        ['true', '"true"'],
        ['false', '"false"'],
        ['in', 'in'], // "in" is not a reserved keyword

        // Keywords with different cases - should be auto-quoted
        ['FROM', '"FROM"'],
        ['Select', '"Select"'],
        ['WHERE', '"WHERE"'],
        ['User', '"User"'],

        // Keywords in dotted paths - only keyword parts should be quoted
        ['mytable.from.id', 'mytable."from".id'],
        ['schema.select.mycolumn', 'schema."select".mycolumn'],
        ['public.user.name', 'public."user".name'],
        ['from.to.where', '"from"."to"."where"'],
        ['table.from.id', '"table"."from".id'],
        ['schema.select.column', 'schema."select"."column"'],

        // Already quoted keywords (unchanged)
        ['"from"', '"from"'],
        ['"select"', '"select"'],
        ['mytable."from".id', 'mytable."from".id'],
        ['"table"."from"."id"', '"table"."from"."id"'],

        // Quoted identifier with dot inside (unchanged)
        ['schema."my.table".mycolumn', 'schema."my.table".mycolumn'],

        // Keywords in quoted identifier context
        ['table."from".id', '"table"."from".id'],
        ['schema."my.table".column', 'schema."my.table"."column"'],
    ]);

    it('rejects invalid identifiers', function (string $input) {
        expect(static fn () => Q::build(Q::n($input))->toSql())->toThrow(QueryBuilderException::class);
    })->with([
        '1column_name',
        '"MyTable.name',
        'My"Table.name',
    ]);
});
