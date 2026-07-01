<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('WindowFuncBuilder', function () {
    it('renders example 1: aggregate over partition', function () {
        $b = Q::select(
            Q::n('depname'),
            Q::n('empno'),
            Q::n('salary'),
            Q\Func::avg(Q::n('salary'))->over()->partitionBy(Q::n('depname')),
        )->from(Q::n('empsalary'));

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT depname, empno, salary, avg(salary) OVER (PARTITION BY depname) FROM empsalary
            SQL,
            null,
        );
    });

    it('renders example 2: rank over partition order by desc', function () {
        $b = Q::select(
            Q::n('depname'),
            Q::n('empno'),
            Q::n('salary'),
            Q\Func::rank()->over()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc(),
        )->from(Q::n('empsalary'));

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT depname, empno, salary,
                   rank() OVER (PARTITION BY depname ORDER BY salary DESC)
            FROM empsalary
            SQL,
            null,
        );
    });

    it('renders example 3: empty over', function () {
        $b = Q::select(
            Q::n('salary'),
            Q\Func::sum(Q::n('salary'))->over(),
        )->from(Q::n('empsalary'));

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT salary, sum(salary) OVER () FROM empsalary
            SQL,
            null,
        );
    });

    it('renders example 4: over order by', function () {
        $b = Q::select(
            Q::n('salary'),
            Q\Func::sum(Q::n('salary'))->over()->orderBy(Q::n('salary')),
        )->from(Q::n('empsalary'));

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT salary, sum(salary) OVER (ORDER BY salary) FROM empsalary
            SQL,
            null,
        );
    });

    it('renders example 5: window func in subquery with multiple order by', function () {
        $b = Q::select(
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
            ->where(Q::n('pos')->lt(Q::int(3)));

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT depname, empno, salary, enroll_date
            FROM
              (SELECT depname, empno, salary, enroll_date,
                      rank() OVER (PARTITION BY depname ORDER BY salary DESC, empno) AS pos
                 FROM empsalary
              ) AS salaries
            WHERE pos < 3
            SQL,
            null,
        );
    });

    it('renders example 6: WINDOW clause referenced by name', function () {
        $b = Q::select(
            Q\Func::sum(Q::n('salary'))->over('w'),
            Q\Func::avg(Q::n('salary'))->over('w'),
        )
            ->from(Q::n('empsalary'))
            ->window('w')->as()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc();

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT sum(salary) OVER w, avg(salary) OVER w
              FROM empsalary
              WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
            SQL,
            null,
        );
    });

    it('renders example 6 variant 1: row_number reusing the named window', function () {
        $b = Q::select(
            Q\Func::sum(Q::n('salary'))->over('w'),
            Q\Func::avg(Q::n('salary'))->over('w'),
            Q\Func::rowNumber()->over('w'),
        )
            ->from(Q::n('empsalary'))
            ->window('w')->as()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc();

        expect($b)->toRenderSql(
            <<<'SQL'
            SELECT sum(salary) OVER w, avg(salary) OVER w, row_number() OVER w
              FROM empsalary
              WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
            SQL,
            null,
        );
    });

    it('applies direction and nulls ordering to an inline window ORDER BY', function () {
        expect(
            Q::select(Q\Func::sum(Q::n('salary'))->over()->orderBy(Q::n('salary'))->asc()->nullsFirst())
                ->from(Q::n('empsalary')),
        )->toRenderSql('SELECT sum(salary) OVER (ORDER BY salary ASC NULLS FIRST) FROM empsalary', null);

        expect(
            Q::select(Q\Func::sum(Q::n('salary'))->over()->orderBy(Q::n('salary'))->nullsLast())
                ->from(Q::n('empsalary')),
        )->toRenderSql('SELECT sum(salary) OVER (ORDER BY salary NULLS LAST) FROM empsalary', null);
    });

    it('applies direction and nulls ordering to a named-window ORDER BY', function () {
        expect(
            Q::select(Q\Func::sum(Q::n('salary'))->over('w'))
                ->from(Q::n('empsalary'))
                ->window('w')->as()->orderBy(Q::n('salary'))->asc()->nullsLast(),
        )->toRenderSql('SELECT sum(salary) OVER w FROM empsalary WINDOW w AS (ORDER BY salary ASC NULLS LAST)', null);

        expect(
            Q::select(Q\Func::sum(Q::n('salary'))->over('w'))
                ->from(Q::n('empsalary'))
                ->window('w')->as()->orderBy(Q::n('salary'))->nullsFirst(),
        )->toRenderSql('SELECT sum(salary) OVER w FROM empsalary WINDOW w AS (ORDER BY salary NULLS FIRST)', null);
    });
});
