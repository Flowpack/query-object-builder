<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\Target;
use Flowpack\QueryObjectBuilder\MySQL\Q;

// The MySQL family is one builder. Each engine's SQL is reached by *different
// construction* (rendering never branches on a dialect flag); the cases below build
// both variants from docs/mysql-mariadb-differences.md and check that validating
// against a Target reports the constructs the target cannot express.

describe('A1 shared row lock', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('renders FOR SHARE for MySQL', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q::n('*'))->from(Q::n('t'))->where(Q::n('id')->eq(Q::arg(1)))->forShare();

        expect($q)->toRenderSql('SELECT * FROM t WHERE id = ? FOR SHARE', [1], $mysql);
        expect($q)->toFailValidationFor($mariaDb, 'FOR SHARE requires MySQL');
    });

    it('renders FOR SHARE OF ... NOWAIT for MySQL', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q::n('*'))->from(Q::n('t'))->where(Q::n('id')->eq(Q::arg(1)))->forShare()->of('t')->nowait();

        expect($q)->toRenderSql('SELECT * FROM t WHERE id = ? FOR SHARE OF t NOWAIT', [1], $mysql);
        // The MySQL-only OF clause is reported even though the wait policy is portable.
        expect($q)->toFailValidationFor($mariaDb, 'FOR SHARE requires MySQL');
    });

    it('renders LOCK IN SHARE MODE for MariaDB', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q::n('*'))->from(Q::n('t'))->where(Q::n('id')->eq(Q::arg(1)))->lockInShareMode();

        expect($q)->toRenderSql('SELECT * FROM t WHERE id = ? LOCK IN SHARE MODE', [1], $mariaDb);
        expect($q)->toFailValidationFor($mysql, 'LOCK IN SHARE MODE requires MariaDB');
    });

    it('shares FOR UPDATE, but OF is MySQL-only even there', function () use ($mysql, $mariaDb) {
        // FOR UPDATE (+ wait policy) validates against both engines.
        $shared = Q::select(Q::n('id'))->from(Q::n('t'))->forUpdate()->skipLocked();
        expect($shared)->toRenderSql('SELECT id FROM t FOR UPDATE SKIP LOCKED', null, $mysql);
        expect($shared)->toRenderSql('SELECT id FROM t FOR UPDATE SKIP LOCKED', null, $mariaDb);

        // Adding OF makes it MySQL-only.
        $withOf = Q::select(Q::n('id'))->from(Q::n('t'))->forUpdate()->of('t');
        expect($withOf)->toRenderSql('SELECT id FROM t FOR UPDATE OF t', null, $mysql);
        expect($withOf)->toFailValidationFor($mariaDb, 'the locking OF clause requires MySQL');
    });
});

describe('A2 upsert proposed-row reference', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('uses the AS new row alias for MySQL', function () use ($mysql, $mariaDb) {
        $q = Q::insertInto(Q::n('t'))
            ->columnNames('id', 'hits')
            ->values(Q::arg(1), Q::arg(2))->as('new')
            ->onDuplicateKeyUpdate()
            ->set('hits', Q::n('new.hits'));

        expect($q)->toRenderSql('INSERT INTO t (id,hits) VALUES (?,?) AS new ON DUPLICATE KEY UPDATE hits = new.hits', [1, 2], $mysql);
        expect($q)->toFailValidationFor($mariaDb, 'the INSERT row alias (AS ...) requires MySQL');
    });

    it('uses the portable VALUES() reference (both engines)', function () use ($mysql, $mariaDb) {
        $q = Q::insertInto(Q::n('t'))
            ->columnNames('id', 'hits')
            ->values(Q::arg(1), Q::arg(2))
            ->onDuplicateKeyUpdate()
            ->set('hits', Q::values('hits'));

        $sql = 'INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits)';
        expect($q)->toRenderSql($sql, [1, 2], $mysql);
        expect($q)->toRenderSql($sql, [1, 2], $mariaDb);
    });
});

describe('A3 JSON path access', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('uses the -> and ->> operators for MySQL', function () use ($mysql, $mariaDb) {
        $extract = Q::select(Q::n('doc')->jsonExtract(Q::string('$.name')))->from(Q::n('t'));
        expect($extract)->toRenderSql("SELECT doc -> '$.name' FROM t", null, $mysql);
        expect($extract)->toFailValidationFor($mariaDb, 'the -> operator requires MySQL');

        $extractText = Q::select(Q::n('doc')->jsonExtractText(Q::string('$.name')))->from(Q::n('t'));
        expect($extractText)->toRenderSql("SELECT doc ->> '$.name' FROM t", null, $mysql);
        expect($extractText)->toFailValidationFor($mariaDb, 'the ->> operator requires MySQL');
    });

    it('uses the function form for MariaDB (both engines)', function () use ($mysql, $mariaDb) {
        $extract = Q::select(Q\Func::jsonExtract(Q::n('doc'), Q::string('$.name')))->from(Q::n('t'));
        expect($extract)->toRenderSql("SELECT JSON_EXTRACT(doc, '$.name') FROM t", null, $mariaDb);
        expect($extract)->toRenderSql("SELECT JSON_EXTRACT(doc, '$.name') FROM t", null, $mysql);

        $extractText = Q::select(Q\Func::jsonUnquote(Q\Func::jsonExtract(Q::n('doc'), Q::string('$.name'))))->from(Q::n('t'));
        expect($extractText)->toRenderSql("SELECT JSON_UNQUOTE(JSON_EXTRACT(doc, '$.name')) FROM t", null, $mariaDb);
    });
});

describe('A4 JSON pretty-print', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('is JSON_PRETTY on MySQL', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q\Func::jsonPretty(Q::n('doc')))->from(Q::n('t'));
        expect($q)->toRenderSql('SELECT JSON_PRETTY(doc) FROM t', null, $mysql);
        expect($q)->toFailValidationFor($mariaDb, 'JSON_PRETTY requires MySQL');
    });

    it('is JSON_DETAILED on MariaDB', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q\Func::jsonDetailed(Q::n('doc')))->from(Q::n('t'));
        expect($q)->toRenderSql('SELECT JSON_DETAILED(doc) FROM t', null, $mariaDb);
        expect($q)->toFailValidationFor($mysql, 'JSON_DETAILED requires MariaDB');
    });
});

describe('B1 RETURNING (MariaDB only)', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('returns from INSERT', function () use ($mysql, $mariaDb) {
        $q = Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))->returning(Q::n('id'), Q::n('created_at'));
        expect($q)->toRenderSql('INSERT INTO t (a) VALUES (?) RETURNING id,created_at', [1], $mariaDb);
        expect($q)->toFailValidationFor($mysql, 'RETURNING requires MariaDB');
    });

    it('returns from DELETE', function () use ($mysql, $mariaDb) {
        $q = Q::deleteFrom(Q::n('t'))->where(Q::n('id')->eq(Q::arg(1)))->returning(Q::n('id'));
        expect($q)->toRenderSql('DELETE FROM t WHERE id = ? RETURNING id', [1], $mariaDb);
        expect($q)->toFailValidationFor($mysql, 'RETURNING requires MariaDB');
    });

    it('returns from REPLACE', function () use ($mysql, $mariaDb) {
        $q = Q::replaceInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))->returning(Q::n('id'));
        expect($q)->toRenderSql('REPLACE INTO t (a) VALUES (?) RETURNING id', [1], $mariaDb);
        expect($q)->toFailValidationFor($mysql, 'RETURNING requires MariaDB');
    });
});

describe('B2 LATERAL (MySQL only)', function () {
    $mysql = Target::mysql();
    $mariaDb = Target::mariaDb();

    it('joins a LATERAL derived table', function () use ($mysql, $mariaDb) {
        $q = Q::select(Q::n('*'))
            ->from(Q::n('orders'))->as('o')
            ->joinLateral(
                Q::select(Q::n('*'))->from(Q::n('items'))->as('i')
                    ->where(Q::n('i.order_id')->eq(Q::n('o.id')))
                    ->limit(Q::int(3)),
            )->as('top')->on(Q::bool(true));

        expect($q)->toRenderSql(
            'SELECT * FROM orders AS o JOIN LATERAL (SELECT * FROM items AS i WHERE i.order_id = o.id LIMIT 3) AS top ON TRUE',
            null,
            $mysql,
        );
        expect($q)->toFailValidationFor($mariaDb, 'LATERAL requires MySQL');
    });
});

describe('B3 WITH before UPDATE / DELETE (MySQL; MariaDB 12.3+)', function () {
    it('gates a leading WITH on DELETE by dialect and version', function () {
        $q = Q::with('stale')->as(Q::select(Q::n('id'))->from(Q::n('sessions'))->where(Q::n('expired')->eq(Q::int(1))))
            ->deleteFrom(Q::n('users'))->where(Q::n('id')->in(Q::select(Q::n('id'))->from(Q::n('stale'))));

        $sql = 'WITH stale AS (SELECT id FROM sessions WHERE expired = 1) DELETE FROM users WHERE id IN (SELECT id FROM stale)';

        // MySQL: any version. MariaDB: only 12.3+.
        expect($q)->toRenderSql($sql, null, Target::mysql());
        expect($q)->toRenderSql($sql, null, Target::mariaDb('12.3'));
        expect($q)->toFailValidationFor(Target::mariaDb('11.4'), 'WITH before DELETE requires MySQL or MariaDB 12.3+');
    });

    it('gates a leading WITH on UPDATE by dialect and version', function () {
        $q = Q::with('bump')->as(Q::select(Q::n('id'))->from(Q::n('flagged')))
            ->update(Q::n('users'))->set('active', Q::int(0))
            ->where(Q::n('id')->in(Q::select(Q::n('id'))->from(Q::n('bump'))));

        expect($q)->toRenderSql(
            'WITH bump AS (SELECT id FROM flagged) UPDATE users SET active = 0 WHERE id IN (SELECT id FROM bump)',
            null,
            Target::mysql(),
        );
        expect($q)->toFailValidationFor(Target::mariaDb('11.4'), 'WITH before UPDATE requires MySQL or MariaDB 12.3+');
    });
});

describe('B4 distribution aggregates (MariaDB only)', function () {
    it('renders MEDIAN as a window function', function () {
        $q = Q\Func::median(Q::n('salary'))->over()->partitionBy(Q::n('dept'));

        expect($q)->toRenderSql('MEDIAN(salary) OVER (PARTITION BY dept)', null, Target::mariaDb());
        expect($q)->toFailValidationFor(Target::mysql(), 'MEDIAN requires MariaDB');
    });
});

describe('C dialect-only functions', function () {
    it('reports MySQL-only functions against MariaDB', function () {
        $q = Q\Func::regexpLike(Q::n('s'), Q::string('^a'));
        expect($q)->toRenderSql("REGEXP_LIKE(s, '^a')", null, Target::mysql());
        expect($q)->toFailValidationFor(Target::mariaDb(), 'REGEXP_LIKE requires MySQL');
    });

    it('reports MariaDB-only functions against MySQL', function () {
        $q = Q\Func::jsonQuery(Q::n('doc'), Q::string('$.a'));
        expect($q)->toRenderSql("JSON_QUERY(doc, '$.a')", null, Target::mariaDb());
        expect($q)->toFailValidationFor(Target::mysql(), 'JSON_QUERY requires MariaDB');
    });
});
