# MySQL & MariaDB dialect

Alongside the PostgreSQL builder, the package ships one **MySQL-family** facade —
`Flowpack\QueryObjectBuilder\MySQL\Q` — covering both **MySQL 8.4 (LTS)** and
**MariaDB 11.x**. The two engines share ~95% of their grammar and all of their
rendering conventions, so a single builder models both; where they genuinely
diverge you construct the engine's own form (see
[Dialect differences](#dialect-differences)), and an opt-in
[target validation](#validating-against-a-target) pass reports any construct the
engine you are targeting cannot express.

It renders the MySQL-family SQL conventions: identifiers are backtick-quoted
(`` `order` ``), parameters are positional `?` placeholders, and string literals
escape both the backslash and the quote.

```php
use Flowpack\QueryObjectBuilder\MySQL\Q;

$q = Q::select(Q::n('id'), Q::n('email'))
    ->from(Q::n('orders'))
    ->where(Q::n('id')->eq(Q::arg(1)));

[$sql, $args] = Q::build($q)->toSql();

echo $sql;        // SELECT id,email FROM orders WHERE id = ?
var_dump($args);  // [1]
```

`toSql()` returns a `[$sql, $args]` pair with positional `?` placeholders and the
argument list to bind — feed both to PDO (`$pdo->prepare($sql)->execute($args)`)
or any layer that speaks MySQL/MariaDB placeholders.

> Note: a `?` placeholder is not reusable, so a named `Q::bind('x')` used twice
> emits two `?` and binds its value into each.

## Facades

- **`Q`** — statements (`select`, `insertInto`, `replaceInto`, `update`,
  `deleteFrom`, `with`, `withRecursive`), identifiers (`n`), literals (`string`,
  `int`, `float`, `bool`, `null`, `default`), parameters (`arg`, `bind`),
  composition (`and`, `or`, `not`, `exists`, `any`, `all`, `case`, `coalesce`,
  `nullif`, `greatest`, `least`, `func`, `cast`, `convert`, `interval`), the
  upsert value reference (`values`), and the window frame bounds (`currentRow`,
  `unboundedPreceding`, `unboundedFollowing`, `preceding`, `following`).
- **`Q\Func`** — SQL functions: aggregates (`count`, `sum`, `avg`, `groupConcat`,
  `jsonArrayAgg`, `bitOr`, `stddevPop`, …), string / numeric / date-time / JSON /
  misc scalars, the window functions (`rowNumber`, `rank`, `lag`, `lead`,
  `firstValue`, …), the special shapes (`GROUP_CONCAT`, `EXTRACT`, `TRIM`), and
  the engine-specific functions (`jsonPretty`, `regexpLike`, … for MySQL;
  `jsonDetailed`, `median`, `percentileCont`/`percentileDisc`, … for MariaDB). It
  is named `Func` (not `Fn`) because `fn` is a reserved keyword in PHP.

Operators are chainable on the expression objects that `Q::n()`, literals and
functions return: `->eq()`, `->neq()`, `->lt()`, `->like()`, `->regexp()`,
`->nullSafeEq()` (`<=>`), `->in()`, `->isNull()`, `->plus()`, `->jsonExtract()`
(`->`, MySQL), the bitwise operators (`->bitAnd()`, `->bitOr()`, `->bitXor()`,
`->shiftLeft()`, `->shiftRight()`, `Q::bitNot()`) and `->memberOf()`
(`x MEMBER OF (arr)`), … Things that read as function calls — `CONCAT`, `POW`,
`CAST`, `JSON_CONTAINS` — are built through the facade (`Q::func` / `Q::cast` / `Q\Func`),
not as chained operators.

## Validating against a target

Rendering is fully determined by how you build the query — it never branches on a
dialect flag. Building without a target renders exactly what you constructed and
never fails on dialect grounds. To check a query against a specific engine (and,
optionally, version), opt in with `withValidateTarget()`:

```php
use Flowpack\QueryObjectBuilder\MySQL\Builder\Target;

$q = Q::select(Q::n('id'))->from(Q::n('t'))->forShare();   // FOR SHARE is MySQL-only

Q::build($q)->withValidateTarget(Target::mysql())->toSql();    // ok
Q::build($q)->withValidateTarget(Target::mariaDb())->toSql();  // throws QueryBuilderException:
// "FOR SHARE requires MySQL, but the query is validated against MariaDB"
```

`Target::mysql($version)` / `Target::mariaDb($version)` carry an optional version.
Version-gated features are checked when a version is supplied — e.g. a leading
`WITH` on `UPDATE`/`DELETE` is valid on MySQL and on MariaDB 12.3+, so it passes
against `Target::mariaDb('12.3')` but fails against `Target::mariaDb('11.4')`. A
target with no version only checks the dialect.

## Examples

### SELECT, joins, grouping

```php
$q = Q::select(Q::n('country'))
    ->select(Q\Func::count(Q::n('*')))->as('n')
    ->from(Q::n('users'))
    ->groupBy(Q::n('country'))->withRollup()
    ->having(Q::n('country')->isNotNull())
    ->orderBy(Q::n('n'))->desc();
```

```sql
SELECT country,COUNT(*) AS n FROM users
GROUP BY country WITH ROLLUP HAVING country IS NOT NULL ORDER BY n DESC
```

```php
$q = Q::select(Q::n('*'))
    ->from(Q::n('users'))->as('u')
    ->leftJoin(Q::n('orders'))->as('o')->on(Q::n('o.user_id')->eq(Q::n('u.id')));
```

```sql
SELECT * FROM users AS u LEFT JOIN orders AS o ON o.user_id = u.id
```

### Locking

`forUpdate()` (optionally `nowait()` / `skipLocked()`) is shared. The shared lock
has a different spelling per engine, so it is two methods:

```php
// MySQL: FOR SHARE (+ of() / nowait() / skipLocked())
Q::select(Q::n('id'))->from(Q::n('t'))->forShare()->of('t')->nowait();
// SELECT id FROM t FOR SHARE OF t NOWAIT

// MariaDB: LOCK IN SHARE MODE
Q::select(Q::n('id'))->from(Q::n('t'))->lockInShareMode();
// SELECT id FROM t LOCK IN SHARE MODE
```

`of()` is MySQL-only even on `FOR UPDATE`; validating a query that uses it against
`Target::mariaDb()` reports it.

### Window functions

```php
$q = Q::select(
    Q::n('depname'),
    Q\Func::rank()->over()->partitionBy(Q::n('depname'))->orderBy(Q::n('salary'))->desc(),
    Q\Func::sum(Q::n('salary'))->over()
        ->partitionBy(Q::n('subject'))->orderBy(Q::n('t'))
        ->rows(Q::unboundedPreceding()),
)->from(Q::n('empsalary'))
    ->window('w')->as()->orderBy(Q::n('salary'));
```

```sql
SELECT depname,
       RANK() OVER (PARTITION BY depname ORDER BY salary DESC),
       SUM(salary) OVER (PARTITION BY subject ORDER BY t ROWS UNBOUNDED PRECEDING)
FROM empsalary WINDOW w AS (ORDER BY salary)
```

### INSERT & upsert

The two engines reference the proposed row differently, so you build each one its
own way:

```php
// MySQL: alias the proposed row with AS new, then reference it as new.col
Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))->as('new')
    ->onDuplicateKeyUpdate()->set('hits', Q::n('new.hits'));
// INSERT INTO t (id,hits) VALUES (?,?) AS new ON DUPLICATE KEY UPDATE hits = new.hits

// Portable: the VALUES(col) function works on both engines
Q::insertInto(Q::n('t'))
    ->columnNames('id', 'hits')->values(Q::arg(1), Q::arg(10))
    ->onDuplicateKeyUpdate()->set('hits', Q::values('hits'));
// INSERT INTO t (id,hits) VALUES (?,?) ON DUPLICATE KEY UPDATE hits = VALUES(hits)
```

`->as('new')` (the row alias) is MySQL-only and is reported when validated against
MariaDB. `Q::values('col')` renders `VALUES(col)`, which both engines accept.

`Q::insertInto(...)->ignore()` renders `INSERT IGNORE`; `Q::replaceInto(...)`
builds a `REPLACE` statement with the same value/column/query surface.

### Multi-table UPDATE / DELETE

```php
Q::update(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->set('t1.col1', Q::n('t2.col1'))
    ->where(Q::n('t2.col2')->isNull());
// UPDATE t1 LEFT JOIN t2 ON t1.id = t2.id SET t1.col1 = t2.col1 WHERE t2.col2 IS NULL

Q::deleteFrom(Q::n('t1'))
    ->leftJoin(Q::n('t2'))->on(Q::n('t1.id')->eq(Q::n('t2.id')))
    ->where(Q::n('t2.id')->isNull());
// DELETE t1.* FROM t1 LEFT JOIN t2 ON t1.id = t2.id WHERE t2.id IS NULL
```

`ORDER BY` and `LIMIT` are available on single-table UPDATE/DELETE only; using
them with a join raises a `QueryBuilderException` when the query is built.

### RETURNING (MariaDB)

```php
Q::insertInto(Q::n('t'))->columnNames('a')->values(Q::arg(1))
    ->returning(Q::n('id'))->as('new_id');
// INSERT INTO t (a) VALUES (?) RETURNING id AS new_id
```

`returning()` is available on INSERT, REPLACE and (single-table) DELETE. It is
MariaDB-only — validating a query that uses it against `Target::mysql()` reports
it.

## Dialect differences

Each row shows how to build the same intent for each engine; the differences are
reported by [target validation](#validating-against-a-target).

| Feature | MySQL | MariaDB |
|---|---|---|
| `LATERAL` from/join (`fromLateral`, `joinLateral`, …) | ✓ | — (no equivalent) |
| Shared row lock | `forShare()` → `FOR SHARE` (+ `of()` / `nowait()` / `skipLocked()`) | `lockInShareMode()` → `LOCK IN SHARE MODE` |
| `RETURNING` on INSERT / REPLACE / DELETE | — | `returning()` (single-table) |
| Leading `WITH` on UPDATE / DELETE | ✓ | MariaDB 12.3+ (off on the 11.x anchor) |
| Upsert proposed-row ref | `->as('new')` + `Q::n('new.col')` | `Q::values('col')` (also works on MySQL) |
| JSON path | `->jsonExtract()` / `->jsonExtractText()` (`->` / `->>`) | `Q\Func::jsonExtract()` / `Q\Func::jsonUnquote()` (also work on MySQL) |
| Pretty-print JSON | `Q\Func::jsonPretty()` | `Q\Func::jsonDetailed()` |
| Dialect-only functions | `regexpLike`, `grouping`, `anyValue`, `jsonSchemaValid*`, `jsonStorage*`, `randomBytes` | `jsonQuery`, `jsonExists`, `median`, `toChar`, `addMonths`, `monthsBetween`, `chr`, `oct` |

Everything else — the SELECT clause set, joins, `WITH ROLLUP`, `HAVING`,
`ORDER BY`, `LIMIT`/`OFFSET`, `UNION`/`INTERSECT`/`EXCEPT`, CTEs, window functions
and frames, `ON DUPLICATE KEY UPDATE`, multi-table UPDATE/DELETE, and the curated
function set — is identical for both engines.

## Limitations

The dialect targets a curated, query-shaping surface; the following are
deliberately out of scope. Anything omitted remains reachable through the raw
`Q::func(name, ...)` escape hatch.

- **Deferred** (in scope later, behind an explicit method): `PARTITION (...)`
  selection, index hints, `STRAIGHT_JOIN` / `NATURAL JOIN`, the `LIMIT off,count`
  short form, MariaDB `OFFSET..FETCH` and recursive-CTE `CYCLE`, the
  `INSERT/REPLACE ... SET` assignment form, MySQL `VALUES ROW()` / `TABLE`
  sources, the comma-separated multi-table list (the `JOIN` form covers it),
  multi-target `DELETE t1,t2 FROM …`, `UPDATE/DELETE IGNORE`, and the heavier
  `JSON_TABLE` column forms (`NESTED PATH`, `DEFAULT…ON EMPTY|ERROR`, `EXISTS
  PATH`).
- **Excluded** (not query shape): `INTO OUTFILE/DUMPFILE/@var`, priority /
  optimizer / result hints (`LOW_PRIORITY`, `SQL_CALC_FOUND_ROWS`, …),
  `PROCEDURE`, `ROWS EXAMINED` / `WAIT n`, `FOR PORTION OF`, spatial / GIS,
  encryption / compression, and XML functions.
- **`UPDATE ... RETURNING`** exists on neither engine within the version anchor.

The full per-production ledger lives in
[`mysql-mariadb-design.md` §12](mysql-mariadb-design.md); the structural engine
differences are catalogued in
[`mysql-mariadb-differences.md`](mysql-mariadb-differences.md).

## Relationship to the PostgreSQL builder

The MySQL-family facade mirrors the PostgreSQL builder's structure (immutability,
type-state transitions, the `Q` / `Q\Func` split) but models the MySQL family's
own SQL rather than PostgreSQL's. Notably: PostgreSQL operators that MySQL/MariaDB
spell as functions are dropped from the expression surface and reached through
`Q\Func` (`::`→`CAST`/`CONVERT`, `||`→`CONCAT`, `^`→`POW`, `@>`→`JSON_CONTAINS`);
PG-only clauses (`DISTINCT ON`, `FULL JOIN`, `GROUPING SETS`/`CUBE`,
`NULLS FIRST/LAST`, materialized CTEs, `SEARCH`) are absent; and `ON CONFLICT`
becomes `onDuplicateKeyUpdate()`, `RETURNING` becomes MariaDB-only, and PG's
`UPDATE ... FROM` / `DELETE ... USING` become the multi-table `JOIN` forms.

It is a separate builder from PostgreSQL: `PostgreSQL\Q` and `MySQL\Q` do not
share types, and a query built with one is rendered by its own `QueryBuilder`.
