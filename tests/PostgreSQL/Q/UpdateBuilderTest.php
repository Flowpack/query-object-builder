<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Builder\UpdateBuilder;
use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('UpdateBuilder', function () {
    describe('examples', function () {
        it('renders example 1', function () {
            $q = Q::update(Q::n('films'))
                ->set('kind', Q::string('Dramatic'))
                ->where(Q::n('kind')->eq(Q::string('Drama')));

            expect($q)->toRenderSql("UPDATE films SET kind = 'Dramatic' WHERE kind = 'Drama'", null);
        });

        it('renders example 2', function () {
            $q = Q::update(Q::n('weather'))
                ->set('temp_lo', Q::n('temp_lo')->plus(Q::int(1)))
                ->set('temp_hi', Q::n('temp_lo')->plus(Q::int(15)))
                ->set('prcp', Q::default())
                ->where(Q::and(
                    Q::n('city')->eq(Q::string('San Francisco')),
                    Q::n('date')->eq(Q::string('2003-07-03')),
                ));

            expect($q)->toRenderSql(
                <<<'SQL'
                UPDATE weather SET temp_lo = temp_lo + 1, temp_hi = temp_lo + 15, prcp = DEFAULT
                  WHERE city = 'San Francisco' AND date = '2003-07-03'
                SQL,
                null,
            );
        });

        it('renders example 2 with returning', function () {
            $q = Q::update(Q::n('weather'))
                ->set('temp_lo', Q::n('temp_lo')->plus(Q::int(1)))
                ->set('temp_hi', Q::n('temp_lo')->plus(Q::int(15)))
                ->set('prcp', Q::default())
                ->where(Q::and(
                    Q::n('city')->eq(Q::string('San Francisco')),
                    Q::n('date')->eq(Q::string('2003-07-03')),
                ))
                ->returning(Q::n('temp_lo'))
                ->returning(Q::n('temp_hi'))
                ->returning(Q::n('prcp'));

            expect($q)->toRenderSql(
                <<<'SQL'
                UPDATE weather SET temp_lo = temp_lo + 1, temp_hi = temp_lo + 15, prcp = DEFAULT
                  WHERE city = 'San Francisco' AND date = '2003-07-03'
                  RETURNING temp_lo, temp_hi, prcp
                SQL,
                null,
            );
        });
    });

    it('renders with', function () {
        $journeyPatterns = Q::n('journey_patterns');

        $q = Q::with('line_journey_pattern')->as(
            Q::select(Q::n('jp.id'))->as('journey_pattern_id')
                ->select(Q::n('l.name'))->as('line_name')
                ->from(Q::n('journey_patterns'))->as('jp')
                ->join(Q::n('routes'))->as('r')->on(Q::n('jp.route_id')->eq(Q::n('r.id')))
                ->join(Q::n('lines'))->as('l')->on(Q::n('r.line_id')->eq(Q::n('l.id')))
                ->where(Q::and(
                    Q::n('l.name')->isNotNull(),
                    Q::n('l.name')->neq(Q::string('')),
                )),
        )->update($journeyPatterns)->as('jp')
            ->set('name', Q::n('ljp.line_name')->concat(Q::string(' - '))->concat(Q::n('jp.name')))
            ->from(Q::n('line_journey_pattern'))->as('ljp')
            ->where(Q::n('ljp.journey_pattern_id')->eq(Q::n('jp.id')));

        expect($q)->toRenderSql(
            <<<'SQL'
            WITH line_journey_pattern AS (
                SELECT jp.id AS journey_pattern_id, l.name AS line_name
                FROM journey_patterns AS jp
                         JOIN routes AS r ON jp.route_id = r.id
                         JOIN lines AS l ON r.line_id = l.id
                WHERE l.name IS NOT NULL
                  AND l.name <> ''
            )
            UPDATE journey_patterns AS jp
            SET name = ljp.line_name || ' - ' || jp.name
            FROM line_journey_pattern AS ljp
            WHERE ljp.journey_pattern_id = jp.id
            SQL,
            null,
        );
    });

    it('renders set map', function () {
        $q = Q::update(Q::n('films'))
            ->setMap([
                'code' => 'UA502',
                'kind' => 'Comedy',
            ])
            ->where(Q::n('kind')->eq(Q::string('Drama')));

        expect($q)->toRenderSql(
            "UPDATE films SET code = $1, kind = $2 WHERE kind = 'Drama'",
            ['UA502', 'Comedy'],
        );
    });

    it('renders set map with reserved keywords', function () {
        $q = Q::update(Q::n('events'))
            ->setMap([
                'from' => '2021-01-01',
                'to' => '2021-01-02',
            ])
            ->where(Q::n('event_id')->eq(Q::string('123')));

        expect($q)->toRenderSql(
            'UPDATE events SET "from" = $1, "to" = $2 WHERE event_id = \'123\'',
            ['2021-01-01', '2021-01-02'],
        );
    });

    it('renders apply if', function () {
        $q = Q::update(Q::n('films'))
            ->set('kind', Q::string('Dramatic'))
            ->where(Q::n('kind')->eq(Q::string('Drama')))
            ->applyIf(true, static fn (UpdateBuilder $q): UpdateBuilder => $q->where(Q::n('archived')->eq(Q::bool(false))));

        expect($q)->toRenderSql(
            "UPDATE films SET kind = 'Dramatic' WHERE kind = 'Drama' AND archived = false",
            null,
        );
    });
});
