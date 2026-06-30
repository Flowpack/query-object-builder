<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Q;

describe('MySQL window functions', function () {
    describe('inline OVER', function () {
        it('renders an aggregate over an empty window', function () {
            expect(
                Q::select(
                    Q::n('salary'),
                    Q\Func::sum(Q::n('salary'))->over(),
                )->from(Q::n('empsalary')),
            )->toRenderSql('SELECT salary, SUM(salary) OVER () FROM empsalary');
        });

        it('renders an aggregate over a partition', function () {
            expect(
                Q::select(
                    Q::n('depname'),
                    Q::n('empno'),
                    Q::n('salary'),
                    Q\Func::avg(Q::n('salary'))->over()->partitionBy(Q::n('depname')),
                )->from(Q::n('empsalary')),
            )->toRenderSql(
                'SELECT depname, empno, salary, AVG(salary) OVER (PARTITION BY depname) FROM empsalary',
            );
        });

        it('renders an aggregate over an order by', function () {
            expect(
                Q::select(
                    Q::n('salary'),
                    Q\Func::sum(Q::n('salary'))->over()->orderBy(Q::n('salary')),
                )->from(Q::n('empsalary')),
            )->toRenderSql('SELECT salary, SUM(salary) OVER (ORDER BY salary) FROM empsalary');
        });

        it('renders rank over partition order by desc', function () {
            expect(
                Q::select(
                    Q::n('depname'),
                    Q::n('empno'),
                    Q::n('salary'),
                    Q\Func::rank()->over()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc(),
                )->from(Q::n('empsalary')),
            )->toRenderSql(
                <<<'SQL'
                SELECT depname, empno, salary,
                       RANK() OVER (PARTITION BY depname ORDER BY salary DESC)
                FROM empsalary
                SQL,
            );
        });

        it('renders multiple partition and order by terms', function () {
            expect(
                Q::select(
                    Q::n('depname'),
                    Q::n('empno'),
                    Q::n('salary'),
                    Q::n('enroll_date'),
                )
                    ->from(
                        Q::select(
                            Q::n('depname'),
                            Q::n('empno'),
                            Q::n('salary'),
                            Q::n('enroll_date'),
                        )
                            ->select(
                                Q\Func::rank()->over()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc()->orderBy(Q::n('empno')),
                            )->as('pos')
                            ->from(Q::n('empsalary')),
                    )->as('salaries')
                    ->where(Q::n('pos')->lt(Q::int(3))),
            )->toRenderSql(
                <<<'SQL'
                SELECT depname, empno, salary, enroll_date
                FROM
                  (SELECT depname, empno, salary, enroll_date,
                          RANK() OVER (PARTITION BY depname ORDER BY salary DESC, empno) AS pos
                     FROM empsalary
                  ) AS salaries
                WHERE pos < 3
                SQL,
            );
        });
    });

    describe('named WINDOW clause', function () {
        it('renders functions referencing a named window', function () {
            expect(
                Q::select(
                    Q\Func::sum(Q::n('salary'))->over('w'),
                    Q\Func::avg(Q::n('salary'))->over('w'),
                    Q\Func::rowNumber()->over('w'),
                )
                    ->from(Q::n('empsalary'))
                    ->window('w')->as()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc(),
            )->toRenderSql(
                <<<'SQL'
                SELECT SUM(salary) OVER w, AVG(salary) OVER w, ROW_NUMBER() OVER w
                  FROM empsalary
                  WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
                SQL,
            );
        });

        it('renders several named windows', function () {
            expect(
                Q::select(
                    Q\Func::rowNumber()->over('w1'),
                    Q\Func::sum(Q::n('val'))->over('w2'),
                )
                    ->from(Q::n('t'))
                    ->window('w1')->as()->orderBy(Q::n('a'))
                    ->window('w2')->as()->partitionBy(Q::n('b')),
            )->toRenderSql(
                'SELECT ROW_NUMBER() OVER w1, SUM(val) OVER w2 FROM t WINDOW w1 AS (ORDER BY a), w2 AS (PARTITION BY b)',
            );
        });

        it('refines a referenced window with extra clauses', function () {
            expect(
                Q::select(
                    Q\Func::sum(Q::n('x'))->over('w')->orderBy(Q::n('y')),
                )
                    ->from(Q::n('t'))
                    ->window('w')->as()->partitionBy(Q::n('g')),
            )->toRenderSql(
                'SELECT SUM(x) OVER (w ORDER BY y) FROM t WINDOW w AS (PARTITION BY g)',
            );
        });
    });

    describe('frame clauses', function () {
        it('renders ROWS UNBOUNDED PRECEDING (running total)', function () {
            expect(
                Q::select(
                    Q\Func::sum(Q::n('val'))->over()
                        ->partitionBy(Q::n('subject'))
                        ->orderBy(Q::n('time'))
                        ->rows(Q::unboundedPreceding()),
                )->from(Q::n('observations')),
            )->toRenderSql(
                'SELECT SUM(val) OVER (PARTITION BY subject ORDER BY time ROWS UNBOUNDED PRECEDING) FROM observations',
            );
        });

        it('renders ROWS BETWEEN n PRECEDING AND n FOLLOWING (moving average)', function () {
            expect(
                Q::select(
                    Q\Func::avg(Q::n('val'))->over()
                        ->partitionBy(Q::n('subject'))
                        ->orderBy(Q::n('time'))
                        ->rows(Q::preceding(Q::int(1)), Q::following(Q::int(1))),
                )->from(Q::n('observations')),
            )->toRenderSql(
                'SELECT AVG(val) OVER (PARTITION BY subject ORDER BY time ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM observations',
            );
        });

        it('renders RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', function () {
            expect(
                Q::select(
                    Q\Func::sum(Q::n('x'))->over()
                        ->orderBy(Q::n('x'))
                        ->range(Q::unboundedPreceding(), Q::currentRow()),
                )->from(Q::n('t')),
            )->toRenderSql(
                'SELECT SUM(x) OVER (ORDER BY x RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM t',
            );
        });

        it('renders a frame inside a named window', function () {
            expect(
                Q::select(
                    Q\Func::sum(Q::n('val'))->over('w'),
                )
                    ->from(Q::n('observations'))
                    ->window('w')->as()->orderBy(Q::n('time'))->rows(Q::unboundedPreceding()),
            )->toRenderSql(
                'SELECT SUM(val) OVER w FROM observations WINDOW w AS (ORDER BY time ROWS UNBOUNDED PRECEDING)',
            );
        });
    });

    describe('window-only functions', function () {
        it('renders the ranking functions over a named window', function () {
            expect(
                Q::select(
                    Q::n('val'),
                    Q\Func::rowNumber()->over('w'),
                    Q\Func::rank()->over('w'),
                    Q\Func::denseRank()->over('w'),
                    Q\Func::percentRank()->over('w'),
                    Q\Func::cumeDist()->over('w'),
                )
                    ->from(Q::n('numbers'))
                    ->window('w')->as()->orderBy(Q::n('val')),
            )->toRenderSql(
                <<<'SQL'
                SELECT val,
                       ROW_NUMBER() OVER w, RANK() OVER w, DENSE_RANK() OVER w,
                       PERCENT_RANK() OVER w, CUME_DIST() OVER w
                FROM numbers
                WINDOW w AS (ORDER BY val)
                SQL,
            );
        });

        it('renders NTILE with a bucket count', function () {
            expect(
                Q::select(
                    Q\Func::ntile(Q::int(4))->over()->orderBy(Q::n('val')),
                )->from(Q::n('numbers')),
            )->toRenderSql('SELECT NTILE(4) OVER (ORDER BY val) FROM numbers');
        });

        it('renders LAG and LEAD with offset and default', function () {
            expect(
                Q::select(
                    Q\Func::lag(Q::n('val'))->over()->orderBy(Q::n('t')),
                    Q\Func::lead(Q::n('val'), Q::int(1), Q::int(0))->over()->orderBy(Q::n('t')),
                )->from(Q::n('t')),
            )->toRenderSql(
                'SELECT LAG(val) OVER (ORDER BY t), LEAD(val, 1, 0) OVER (ORDER BY t) FROM t',
            );
        });

        it('renders FIRST_VALUE, LAST_VALUE and NTH_VALUE', function () {
            expect(
                Q::select(
                    Q\Func::firstValue(Q::n('val'))->over('w'),
                    Q\Func::lastValue(Q::n('val'))->over('w'),
                    Q\Func::nthValue(Q::n('val'), Q::int(2))->over('w'),
                )
                    ->from(Q::n('t'))
                    ->window('w')->as()->orderBy(Q::n('t')),
            )->toRenderSql(
                <<<'SQL'
                SELECT FIRST_VALUE(val) OVER w, LAST_VALUE(val) OVER w, NTH_VALUE(val, 2) OVER w
                FROM t
                WINDOW w AS (ORDER BY t)
                SQL,
            );
        });

        it('renders COUNT(*) as a window function', function () {
            expect(
                Q::select(
                    Q\Func::count(Q::n('*'))->over()->partitionBy(Q::n('country')),
                )->from(Q::n('sales')),
            )->toRenderSql('SELECT COUNT(*) OVER (PARTITION BY country) FROM sales');
        });
    });
});
