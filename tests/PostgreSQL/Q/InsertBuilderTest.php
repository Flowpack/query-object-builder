<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\QueryBuilderException;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('InsertBuilder', function () {
    describe('examples', function () {
        it('renders example 1', function () {
            $q = Q::insertInto(Q::n('films'))
                ->values(Q::string('UA502'), Q::string('Bananas'), Q::int(105), Q::string('1971-07-13'), Q::string('Comedy'), Q::string('82 minutes'));

            expect($q)->toRenderSql(
                "INSERT INTO films VALUES ('UA502', 'Bananas', 105, '1971-07-13', 'Comedy', '82 minutes')",
                null,
            );
        });

        it('renders example 2', function () {
            $q = Q::insertInto(Q::n('films'))
                ->columnNames('code', 'title', 'did', 'date_prod', 'kind')
                ->values(Q::string('T_601'), Q::string('Yojimbo'), Q::int(106), Q::string('1961-06-16'), Q::string('Drama'));

            expect($q)->toRenderSql(
                "INSERT INTO films (code, title, did, date_prod, kind) VALUES ('T_601', 'Yojimbo', 106, '1961-06-16', 'Drama')",
                null,
            );
        });

        it('renders example 3a', function () {
            $q = Q::insertInto(Q::n('films'))
                ->values(Q::string('UA502'), Q::string('Bananas'), Q::int(105), Q::default(), Q::string('Comedy'), Q::string('82 minutes'));

            expect($q)->toRenderSql(
                "INSERT INTO films VALUES ('UA502', 'Bananas', 105, DEFAULT, 'Comedy', '82 minutes')",
                null,
            );
        });

        it('renders example 3b', function () {
            $q = Q::insertInto(Q::n('films'))
                ->columnNames('code', 'title', 'did', 'date_prod', 'kind')
                ->values(Q::string('T_601'), Q::string('Yojimbo'), Q::int(106), Q::default(), Q::string('Drama'));

            expect($q)->toRenderSql(
                "INSERT INTO films (code, title, did, date_prod, kind) VALUES ('T_601', 'Yojimbo', 106, DEFAULT, 'Drama')",
                null,
            );
        });

        it('renders example 4', function () {
            $q = Q::insertInto(Q::n('films'))->defaultValues();

            expect($q)->toRenderSql('INSERT INTO films DEFAULT VALUES', null);
        });

        it('renders example 5', function () {
            $q = Q::insertInto(Q::n('films'))
                ->columnNames('code', 'title', 'did', 'date_prod', 'kind')
                ->values(Q::string('B6717'), Q::string('Tampopo'), Q::int(110), Q::string('1985-02-10'), Q::string('Comedy'))
                ->values(Q::string('HG120'), Q::string('The Dinner Game'), Q::int(140), Q::default(), Q::string('Comedy'));

            expect($q)->toRenderSql(
                <<<'SQL'
                INSERT INTO films (code, title, did, date_prod, kind) VALUES
                    ('B6717', 'Tampopo', 110, '1985-02-10', 'Comedy'),
                    ('HG120', 'The Dinner Game', 140, DEFAULT, 'Comedy')
                SQL,
                null,
            );
        });

        it('renders example 6', function () {
            $q = Q::insertInto(Q::n('films'))
                ->query(Q::select(Q::n('*'))->from(Q::n('tmp_films'))->where(Q::n('date_prod')->lt(Q::string('2004-05-07'))));

            expect($q)->toRenderSql(
                "INSERT INTO films SELECT * FROM tmp_films WHERE date_prod < '2004-05-07'",
                null,
            );
        });

        it('renders example 7a', function () {
            $q = Q::insertInto(Q::n('tictactoe'))
                ->columnNames('game', 'board[1:3][1:3]')
                ->values(Q::int(1), Q::string('{{" "," "," "},{" "," "," "},{" "," "," "}}'));

            expect($q)->toRenderSql(
                <<<'SQL'
                INSERT INTO tictactoe (game, board[1:3][1:3])
                    VALUES (1, '{{" "," "," "},{" "," "," "},{" "," "," "}}')
                SQL,
                null,
            );
        });

        it('renders example 7b', function () {
            $q = Q::insertInto(Q::n('tictactoe'))
                ->columnNames('game', 'board')
                ->values(Q::int(2), Q::string('{{X," "," "},{" ",O," "},{" ",X," "}}'));

            expect($q)->toRenderSql(
                <<<'SQL'
                INSERT INTO tictactoe (game, board)
                    VALUES (2, '{{X," "," "},{" ",O," "},{" ",X," "}}')
                SQL,
                null,
            );
        });

        it('renders example 8', function () {
            $q = Q::insertInto(Q::n('distributors'))
                ->columnNames('did', 'dname')
                ->values(Q::default(), Q::string('XYZ Widgets'))
                ->returning(Q::n('did'));

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (DEFAULT, 'XYZ Widgets') RETURNING did",
                null,
            );
        });

        it('renders example 8 - multiple returning', function () {
            $q = Q::insertInto(Q::n('distributors'))
                ->columnNames('did', 'dname')
                ->values(Q::default(), Q::string('XYZ Widgets'))
                ->returning(Q::n('did'), Q::n('dname'));

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (DEFAULT, 'XYZ Widgets') RETURNING did, dname",
                null,
            );
        });

        it('renders example 8 - returning an aliased column', function () {
            $q = Q::insertInto(Q::n('distributors'))
                ->columnNames('did', 'dname')
                ->values(Q::default(), Q::string('XYZ Widgets'))
                ->returning(Q::n('did'))->as('id');

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (DEFAULT, 'XYZ Widgets') RETURNING did AS id",
                null,
            );
        });

        it('renders example 9', function () {
            $employeesLog = Q::n('employees_log');

            $q = Q::with('upd')->as(
                Q::update(Q::n('employees'))
                    ->set('sales_count', Q::n('sales_count')->plus(Q::int(1)))
                    ->where(Q::n('id')->eq(
                        Q::select(Q::n('sales_person'))->from(Q::n('accounts'))->where(Q::n('name')->eq(Q::string('Acme Corporation'))),
                    ))
                    ->returning(Q::n('*')),
            )
                ->insertInto($employeesLog)
                ->query(Q::select(Q::n('*'), Q::n('current_timestamp'))->from(Q::n('upd')));

            expect($q)->toRenderSql(
                <<<'SQL'
                WITH upd AS (
                  UPDATE employees SET sales_count = sales_count + 1 WHERE id =
                    (SELECT sales_person FROM accounts WHERE name = 'Acme Corporation')
                    RETURNING *
                )
                INSERT INTO employees_log SELECT *, current_timestamp FROM upd
                SQL,
                null,
            );
        });

        it('renders example 10', function () {
            $q = Q::insertInto(Q::n('distributors'))->columnNames('did', 'dname')
                ->values(Q::int(5), Q::string('Gizmo Transglobal'))
                ->values(Q::int(6), Q::string('Associated Computing,Inc'))
                ->onConflict(Q::n('did'))->doUpdate()->set('dname', Q::n('EXCLUDED.dname'));

            expect($q)->toRenderSql(
                <<<'SQL'
                INSERT INTO distributors (did, dname)
                VALUES (5, 'Gizmo Transglobal'), (6, 'Associated Computing,Inc')
                ON CONFLICT (did) DO UPDATE SET dname = EXCLUDED.dname
                SQL,
                null,
            );
        });

        it('renders example 11', function () {
            $q = Q::insertInto(Q::n('distributors'))->columnNames('did', 'dname')
                ->values(Q::int(7), Q::string('Redline GmbH'))
                ->onConflict(Q::n('did'))->doNothing();

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (7, 'Redline GmbH') ON CONFLICT (did) DO NOTHING",
                null,
            );
        });

        it('renders example 12a', function () {
            $q = Q::insertInto(Q::n('distributors'))->as('d')->columnNames('did', 'dname')
                ->values(Q::int(8), Q::string('Anvil Distribution'))
                ->onConflict(Q::n('did'))->doUpdate()
                ->set('dname', Q::n('EXCLUDED.dname')->concat(Q::string(' (formerly '))->concat(Q::n('d.dname'))->concat(Q::string(')')))
                ->where(Q::n('d.zipcode')->neq(Q::string('21201')));

            expect($q)->toRenderSql(
                <<<'SQL'
                INSERT INTO distributors AS d (did, dname)
                VALUES (8, 'Anvil Distribution')
                ON CONFLICT (did) DO UPDATE
                    SET dname = EXCLUDED.dname || ' (formerly ' || d.dname || ')'
                WHERE d.zipcode <> '21201'
                SQL,
                null,
            );
        });

        it('renders example 12b', function () {
            $q = Q::insertInto(Q::n('distributors'))->columnNames('did', 'dname')
                ->values(Q::int(9), Q::string('Antwerp Design'))
                ->onConflict()->onConstraint('distributors_pkey')->doNothing();

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (9, 'Antwerp Design') ON CONFLICT ON CONSTRAINT distributors_pkey DO NOTHING",
                null,
            );
        });

        it('renders example 13', function () {
            $q = Q::insertInto(Q::n('distributors'))->columnNames('did', 'dname')
                ->values(Q::int(10), Q::string('Conrad International'))
                ->onConflict(Q::n('did'))->where(Q::n('is_active'))->doNothing();

            expect($q)->toRenderSql(
                "INSERT INTO distributors (did, dname) VALUES (10, 'Conrad International') ON CONFLICT (did) WHERE is_active DO NOTHING",
                null,
            );
        });
    });

    it('renders set map', function () {
        $q = Q::insertInto(Q::n('films'))
            ->setMap([
                'code' => 'UA502',
                'title' => 'Bananas',
                'did' => 105,
                'date_prod' => '1971-07-13',
                'kind' => 'Comedy',
                'length' => '82 minutes',
            ]);

        expect($q)->toRenderSql(
            'INSERT INTO films (code,date_prod,did,kind,length,title) VALUES ($1, $2, $3, $4, $5, $6)',
            ['UA502', '1971-07-13', 105, 'Comedy', '82 minutes', 'Bananas'],
        );
    });

    it('renders set map with reserved keywords', function () {
        $q = Q::insertInto(Q::n('events'))
            ->setMap([
                'event_id' => '123',
                'from' => '2021-01-01',
                'to' => '2021-01-02',
                'user' => 'john',
            ]);

        expect($q)->toRenderSql(
            'INSERT INTO events (event_id,"from","to","user") VALUES ($1, $2, $3, $4)',
            ['123', '2021-01-01', '2021-01-02', 'john'],
        );
    });

    it('renders values with args', function () {
        $q = Q::insertInto(Q::n('films'))
            ->columnNames('code', 'date_prod', 'did', 'kind', 'length', 'title')
            ->values(
                Q::arg('UA502'),
                Q::arg('1971-07-13'),
                Q::arg(105),
                Q::arg('Comedy'),
                Q::arg('82 minutes'),
                Q::arg('Bananas'),
            );

        expect($q)->toRenderSql(
            'INSERT INTO films (code,date_prod,did,kind,length,title) VALUES ($1, $2, $3, $4, $5, $6)',
            ['UA502', '1971-07-13', 105, 'Comedy', '82 minutes', 'Bananas'],
        );
    });

    it('renders multiple value rows', function () {
        $q = Q::insertInto(Q::n('films'))
            ->columnNames('code', 'date_prod', 'did', 'kind', 'length', 'title')
            ->values(Q::string('UA502'), Q::string('1971-07-13'), Q::int(105), Q::string('Comedy'), Q::string('82 minutes'), Q::string('Bananas'))
            ->values(Q::string('T_601'), Q::string('1962-12-10'), Q::int(106), Q::string('Drama'), Q::string('227 minutes'), Q::string('Lawrence of Arabia'));

        expect($q)->toRenderSql(
            <<<'SQL'
            INSERT INTO films (code, date_prod, did, kind, length, title)
            VALUES ('UA502', '1971-07-13', 105, 'Comedy', '82 minutes', 'Bananas'),
                   ('T_601', '1962-12-10', 106, 'Drama', '227 minutes', 'Lawrence of Arabia')
            SQL,
            null,
        );
    });

    it('rejects setting both values and a query', function () {
        $q = Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::int(1))->query(Q::select(Q::int(2)));

        expect(static fn () => Q::build($q)->toSql())
            ->toThrow(QueryBuilderException::class, 'insert: cannot set both values and query');
    });

    it('rejects setting both an ON CONFLICT constraint name and targets', function () {
        $q = Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::int(1))
            ->onConflict(Q::n('id'))->onConstraint('t_pkey')->doNothing();

        expect(static fn () => Q::build($q)->toSql())
            ->toThrow(QueryBuilderException::class, 'insert: cannot set both conflict constraint name and targets');
    });

    it('upserts on multiple conflict targets updating multiple columns', function () {
        $q = Q::insertInto(Q::n('t'))->columnNames('a', 'b')
            ->values(Q::int(1), Q::int(2))
            ->onConflict(Q::n('a'), Q::n('b'))->doUpdate()
            ->set('a', Q::n('EXCLUDED.a'))
            ->set('b', Q::n('EXCLUDED.b'));

        expect($q)->toRenderSql(
            'INSERT INTO t (a, b) VALUES (1, 2) ON CONFLICT (a,b) DO UPDATE SET a = EXCLUDED.a,b = EXCLUDED.b',
            null,
        );
    });
});
