# MySQL vs MariaDB — structural SQL differences

A catalogue of every place MySQL and MariaDB **structurally** diverge, based on the
port analysis. Its purpose is to inform how the query builder should model the two
engines: which differences are cheap token swaps, which are whole-clause
availability, and which are just function presence.

**Version anchors:** MySQL **8.4 (LTS)**, MariaDB **11.x** (11.8 LTS). A few gates
below are version-dependent and called out as such.

Each entry shows the *same intent* rendered for both engines. Differences are
grouped by how a single, dialect-parameterised builder would handle them:

- **Render-branch** — one builder method, a dialect flag picks the spelling.
- **Availability** — a clause/feature exists on one engine only (method present but
  validated against the configured dialect, or absent).
- **Function presence** — same call shape, available on one side only.

---

## A. Render-branches — same query shape, different tokens

The cheap ones: identical builder call, a flag selects the SQL text. No effect on
composition.

### A1. Shared row lock

```sql
-- MySQL
SELECT * FROM t WHERE id = ?  FOR SHARE;
SELECT * FROM t WHERE id = ?  FOR SHARE OF t NOWAIT;   -- OF / wait-policy allowed

-- MariaDB
SELECT * FROM t WHERE id = ?  LOCK IN SHARE MODE;      -- no OF, no wait-policy
```

`FOR UPDATE` (optionally `NOWAIT` / `SKIP LOCKED`) is **identical** in both; only the
*shared* lock diverges. `OF tbl` is MySQL-only (even on `FOR UPDATE`).

### A2. Upsert — reference to the proposed row

```sql
-- MySQL (8.0.19+): row alias `AS new`, referenced as new.col
INSERT INTO t (id, hits) VALUES (?, ?) AS new
  ON DUPLICATE KEY UPDATE hits = new.hits;

-- MariaDB: no alias, VALUES(col) function
INSERT INTO t (id, hits) VALUES (?, ?)
  ON DUPLICATE KEY UPDATE hits = VALUES(hits);
```

MySQL emits an extra `AS new` after the value rows and refers to `new.col`; MariaDB
has no alias and wraps the column in `VALUES(...)`. (MySQL's `VALUES(col)` still
works but is deprecated since 8.0.20; MariaDB has no `AS new`.) The rest of the
`ON DUPLICATE KEY UPDATE` structure is identical.

### A3. JSON path access

```sql
-- MySQL: operators
SELECT doc ->  '$.name' FROM t;
SELECT doc ->> '$.name' FROM t;

-- MariaDB: function form (no -> / ->> operators — MDEV-13594)
SELECT JSON_EXTRACT(doc, '$.name') FROM t;
SELECT JSON_UNQUOTE(JSON_EXTRACT(doc, '$.name')) FROM t;
```

`->>` is the most structural: MariaDB needs the nested
`JSON_UNQUOTE(JSON_EXTRACT(...))`. This is the case that would force a large
expression-layer fork under a strict per-dialect type split; under a dialect flag
it is a single branch.

### A4. JSON pretty-print

```sql
-- MySQL
SELECT JSON_PRETTY(doc) FROM t;

-- MariaDB
SELECT JSON_DETAILED(doc) FROM t;
```

Same shape, different function name.

---

## B. Availability — a whole clause/feature on one engine only

Modelled as a method that is present but validated against the configured dialect
(or simply absent on the engine that lacks it).

### B1. `RETURNING` — MariaDB only

```sql
-- MariaDB
INSERT INTO t (a) VALUES (?) RETURNING id, created_at;
DELETE FROM t WHERE id = ? RETURNING id;
REPLACE INTO t (a) VALUES (?) RETURNING id;

-- MySQL: no equivalent — separate round-trip
INSERT INTO t (a) VALUES (?);
SELECT LAST_INSERT_ID();
```

MariaDB supports `RETURNING` on INSERT, REPLACE and (single-table) DELETE. Neither
engine has `UPDATE ... RETURNING` within the version anchor.

### B2. `LATERAL` — MySQL only

```sql
-- MySQL (8.0.14+)
SELECT * FROM orders o
  JOIN LATERAL (SELECT * FROM items i WHERE i.order_id = o.id LIMIT 3) AS top ON TRUE;

-- MariaDB: no LATERAL — must be rewritten (correlated subquery / different shape);
-- there is no 1:1 equivalent.
```

### B3. Leading `WITH` before UPDATE / DELETE — MySQL only (in the 11.x anchor)

```sql
-- MySQL
WITH stale AS (SELECT id FROM sessions WHERE expired = 1)
DELETE FROM users WHERE id IN (SELECT id FROM stale);

-- MariaDB 11.x: WITH only before SELECT (and INSERT via a feeding SELECT) — inline it
DELETE FROM users WHERE id IN (SELECT id FROM sessions WHERE expired = 1);
```

Version gate: MariaDB 12.3+ lifts this restriction. `WITH ... SELECT` works in both.

### B4. Ordered-set / distribution aggregates — MariaDB only

```sql
-- MariaDB
SELECT MEDIAN(salary) OVER (PARTITION BY dept) FROM emp;
SELECT PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY salary) OVER (PARTITION BY dept) FROM emp;
SELECT PERCENTILE_DISC(0.9) WITHIN GROUP (ORDER BY salary) OVER (PARTITION BY dept) FROM emp;

-- MySQL: none of these exist
```

`PERCENTILE_CONT` / `PERCENTILE_DISC` are structurally special even within MariaDB
(the `WITHIN GROUP (ORDER BY …)` ordered-set shape).

### B5. `OFFSET … FETCH` — MariaDB only

```sql
-- MariaDB
SELECT * FROM t ORDER BY id OFFSET 10 ROWS FETCH FIRST 5 ROWS ONLY;   -- also WITH TIES

-- MySQL: LIMIT only
SELECT * FROM t ORDER BY id LIMIT 5 OFFSET 10;
```

`LIMIT … OFFSET …` works in **both**; `FETCH FIRST … ONLY / WITH TIES` is a MariaDB
extra.

### B6. Recursive-CTE `CYCLE` — MariaDB only

```sql
-- MariaDB (relaxed, non-standard)
WITH RECURSIVE g AS (
  SELECT ... UNION ... SELECT ...
) CYCLE id RESTRICT
SELECT * FROM g;

-- MySQL: no CYCLE / SEARCH clauses at all
```

---

## C. Function *sets* that differ (presence only, not structural)

Same call shape; available on one side only. A single builder treats these as
union membership plus a dialect check (or documentation).

| MySQL-only | MariaDB-only |
|---|---|
| `REGEXP_LIKE(s, p)` | `JSON_QUERY(doc, path)` |
| `GROUPING(c)` | `JSON_EXISTS(doc, path)` |
| `ANY_VALUE(c)` | `MEDIAN(x)` |
| `JSON_SCHEMA_VALID(schema, doc)`, `JSON_SCHEMA_VALIDATION_REPORT(...)` | `TO_CHAR(x[, fmt])` |
| `JSON_STORAGE_SIZE(doc)`, `JSON_STORAGE_FREE(doc)` | `ADD_MONTHS(d, n)`, `MONTHS_BETWEEN(a, b)` |
| `RANDOM_BYTES(n)` | `CHR(n)`, `OCT(n)` |

> This table is version-sensitive — re-check against your exact MySQL 8.4 /
> MariaDB 11.x builds. The structural items in sections A and B are the confident
> ones.

---

## What is identical

So the divergence stays small, the shared surface is worth stating:

- Backtick identifier quoting, positional `?` placeholders, string escaping
  (backslash + doubled quote).
- `SELECT`, all join variants, `WHERE`, `HAVING`.
- `GROUP BY … WITH ROLLUP` — both use `WITH ROLLUP` (MySQL has no `ROLLUP(...)`
  form).
- `ORDER BY`, `LIMIT` / `OFFSET`.
- `UNION` / `INTERSECT` / `EXCEPT` (± `ALL`).
- CTEs before `SELECT` (and before INSERT via a feeding SELECT).
- The entire window-function and frame-clause grammar (`OVER`, named `WINDOW`,
  `ROWS`/`RANGE` frames).
- `ON DUPLICATE KEY UPDATE` structure (only the proposed-row reference in A2
  differs).
- Multi-table `UPDATE` / `DELETE`; `INSERT` / `REPLACE` / `INSERT IGNORE`.
- The whole curated scalar / aggregate / window function set.

---

## Summary — the divergence surface

| # | Difference | Kind | Handling in a unified builder |
|---|---|---|---|
| A1 | shared lock (`FOR SHARE` vs `LOCK IN SHARE MODE`, `OF`/wait) | render-branch | flag picks tokens |
| A2 | upsert row-ref (`new.col` + `AS new` vs `VALUES(col)`) | render-branch | flag picks tokens |
| A3 | JSON path (`->`/`->>` vs `JSON_EXTRACT`/`JSON_UNQUOTE`) | render-branch | flag picks tokens |
| A4 | pretty-print (`JSON_PRETTY` vs `JSON_DETAILED`) | render-branch | flag picks name |
| B1 | `RETURNING` (MariaDB) | availability | validated method |
| B2 | `LATERAL` (MySQL) | availability | validated method |
| B3 | `WITH` before UPDATE/DELETE (MySQL; MariaDB 12.3+) | availability | validated method (version gate) |
| B4 | `MEDIAN` / `PERCENTILE_CONT`/`DISC` `WITHIN GROUP` (MariaDB) | availability | validated method |
| B5 | `OFFSET … FETCH` (MariaDB) | availability | validated method |
| B6 | recursive-CTE `CYCLE` (MariaDB) | availability | validated method |
| C | ~11 dialect-only functions | presence | union + dialect check |

The entire structural divergence is **~10 items — 4 of them mere token swaps**.
That small, well-known surface is what a `Dialect` flag plus a handful of validated
guards would cover, and it is why merging the MySQL family into one builder (while
keeping PostgreSQL a separate builder) is proportionate: the engines share their
rendering primitives and ~95% of the grammar, and the differences that remain are
either one-line branches or a short list of feature guards.

See also: [`mysql-mariadb.md`](mysql-mariadb.md) (usage) and
[`mysql-mariadb-design.md`](mysql-mariadb-design.md) (the full design + §12 coverage
ledger).
