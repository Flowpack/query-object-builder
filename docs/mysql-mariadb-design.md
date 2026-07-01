# MySQL / MariaDB dialect — design spec

Blueprint for adding MySQL + MariaDB query building alongside the existing
PostgreSQL builder. Synthesised from the official-doc research sweep; the
exhaustive per-area findings live next to this file's source material (the
`scratchpad/research/*.md` extracts: `select`, `insert`, `replace`, `update`,
`delete`, `with-cte`, `expressions`, `functions-mysql`, `functions-mariadb`).

**Version anchors:** MySQL **8.4 (LTS)**, MariaDB **11.x GA** (11.8 LTS).
Every feature gated below the anchor is treated as *not available*.

> Status: **implemented** as a single MySQL-family builder in `src/MySQL` (there is
> no `src/MariaDB`). All staged areas (§9) have landed and are green on
> `vendor/bin/pest` + `vendor/bin/phpstan analyse` (level max): foundation,
> expression layer, SELECT, window functions, DML, the curated function facade, and
> the engine divergences. Usage docs live in `mysql-mariadb.md`; the structural
> engine differences are catalogued in `mysql-mariadb-differences.md`; the coverage
> ledger (§12) records every deferred/excluded production.
>
> **Architecture note — supersedes the per-variant framing throughout the body
> (§2, §4, §8, §9, §11).** Those sections are the original blueprint and still read
> as two facades (`MySQL\Q` + `MariaDB\Q`), abstract base builders, per-class
> `writeSql` render-branches and `Q::inserted()`; none of that is how it landed.
> The two engines were collapsed into one builder rather than the originally-planned
> per-variant subclasses. Rendering is fully determined by construction (never a dialect flag),
> so engine-divergent SQL comes from *different construction* — `forShare()` vs
> `lockInShareMode()`, `->as('new')` + `Q::n('new.col')` vs `Q::values('col')`,
> the `->jsonExtract()` operator vs `Q\Func::jsonExtract()`. Each divergent
> construct calls `$sb->requireDialect(...)` / `requireAnyDialect(...)` while
> rendering; validation is opt-in via
> `Q::build($q)->withValidateTarget(Target::mysql()|mariaDb($version))`, which
> reports (never silently changes) the constructs the target cannot express.
> `Requirement` carries an optional `[gteVersion, ltVersion)` window for
> version-gated features (e.g. leading `WITH` on UPDATE/DELETE = MySQL, or MariaDB
> 12.3+). The §8 split matrix still lists *which* engine each construct belongs to;
> only its "handling" column (render-branch / per-class writeSql) is superseded.

---

## 1. Key findings that shape the design

1. **MySQL and MariaDB share rendering primitives.** Both quote identifiers with
   backticks `` `x` ``, bind with positional `?`, and escape strings with
   backslash *and* doubled quotes. So at the `SqlBuilder` / leaf-expression level
   there is **one** MySQL-family dialect, not two. (Contrast PostgreSQL: `"x"`,
   `$1`, `E'...'`.)
2. **No containment between the two engines.** MySQL-only: `LATERAL`, `FOR SHARE`
   + `OF tbl`, `REGEXP_LIKE()`, `GROUPING()`, `ANY_VALUE()`, `JSON_SCHEMA_*`,
   `JSON_PRETTY`, the `AS new` upsert row-alias, `MEMBER OF`. MariaDB-only:
   `RETURNING` (INSERT/REPLACE/DELETE), `CYCLE`, `OFFSET..FETCH`, `ROWS EXAMINED`,
   `WAIT n`, `LOCK IN SHARE MODE` as the only shared lock, `JSON_QUERY`,
   `JSON_DETAILED`, `MEDIAN`, `PERCENTILE_CONT/DISC`, Oracle-compat funcs. So the
   split must be modelled per-feature, not as inheritance.
3. **The split surface is small and of two kinds:**
   - **Render-time keyword choice** — e.g. `FOR SHARE` (MySQL) vs
     `LOCK IN SHARE MODE` (MariaDB). Same builder method, dialect-branched
     `writeSql`.
   - **Feature availability** — e.g. `LATERAL` (MySQL-only), `RETURNING`
     (MariaDB-only). The capability simply does not exist on the other engine.
4. **The expression/operator layer is a rewrite, not a port.** Most PG operators
   become **function calls** in MySQL/MariaDB: `::`→`CAST(x AS t)`, `||`→`CONCAT`,
   `^`→`POW`, `@>`→`JSON_CONTAINS`, `#>`/`#>>`→`JSON_EXTRACT`/`JSON_UNQUOTE`.
   A handful are dropped (`ILIKE`, `SIMILAR TO`, POSIX `~`-family as distinct
   case variants) and a few added (`<=>`, `REGEXP`, `MEMBER OF`). Operator
   precedence differs and needs its own table.

---

## 2. Architecture

A **new namespace family** (`MySQL` + `MariaDB`), built by **duplicating the
PostgreSQL builder and adapting it** — *not* by extracting a shared `Common/`
core yet. (Rule of three: PG is the first dialect, MySQL the second; extract the
shared plumbing only once two working dialects exist to factor against.)

```
src/MySQL/
  Q.php               # MySQL facade (static, like PostgreSQL\Q)
  Q/Func.php          # MySQL function facade
  Builder/            # shared primitives + machinery + abstract base builders
                      #   + the MySQL concrete builder ladder
src/MariaDB/
  Q.php               # MariaDB facade
  Q/Func.php          # MariaDB function facade (+ JSON_QUERY/MEDIAN/PERCENTILE_*)
  Builder/            # the MariaDB concrete builder ladder (RETURNING, …),
                      #   reusing the primitives + abstract bases from src/MySQL/Builder
```

### Dialect-native design

The canonical rule lives in **AGENTS.md** ("Dialect-native design" + the
no-cross-dialect-framing comment rule): each dialect models its own SQL —
operators are chainable expression methods, functions are built via the facade
(`Q::func` / `Q::cast` / `Q\Func`), and comments never frame one dialect against
another. Consequence here: the MySQL `ExpBase` carries only MySQL's actual
operators, and a final comment sweep is part of stage 8.

### Variant modelling — decided: per-variant subclasses (compile-time safe)

Each engine gets its own concrete builder ladder, so a method that is invalid on
an engine **does not exist** on that engine's builder — misuse is a type error
(IDE + PHPStan), not a runtime surprise. Consequences:

- **No `MySqlVariant` flag, no build-time gating.** Class identity carries the
  dialect: render-time splits become per-class `writeSql` (`FOR SHARE` vs
  `LOCK IN SHARE MODE`; `new.col` vs `VALUES(col)`); availability splits are just
  the presence/absence of a method. This *removes* a runtime flag and a set of
  validation branches the unified approach would have needed — so the net
  complexity gap is smaller than the class count suggests.
- **Only the builder ladder forks; the plumbing is shared.** The rendering
  primitives (`SqlBuilder` with backtick/`?`/escaping, `Keywords`, `Literals`,
  `IdentExp`) and the machinery (`SqlWriter`/`InnerSqlWriter`/`QueryBuilder`/
  `Precedence`/structural value objects) are mechanically identical for both
  engines and live **once** in `src/MySQL/Builder/`. `src/MariaDB/Builder/` adds
  only the subclasses that genuinely differ.
- **Shared logic stays single-sourced.** Generic clause logic + `derive()` +
  rendering live on an abstract base builder (+ traits); per-variant classes are
  mostly covariant return-type shims plus the 2-3 methods that actually diverge
  (MySQL: `fromLateral`/`joinLateral`/…/`of()`; MariaDB: `returning()` on
  INSERT/REPLACE/DELETE). What's duplicated is *signatures*, not behaviour.
- **Cost:** ~20-25 extra thin classes (the SELECT + DML ladders fork because the
  type-state subbuilders `extends` the base). Accepted deliberately: it lets the
  engines diverge freely as they drift (MariaDB 12.3 WITH-before-UPDATE, MariaDB
  13 UPDATE RETURNING, MySQL-only optimizer features) without threading version
  flags through shared code.

The exact subbuilder hierarchy (how far the MySQL-only `lateral` methods thread
down the transition chain vs. living only on the entry builder) is settled in the
SELECT implementation stage.

### Rendering primitives (the MySQL-family `SqlBuilder` / leaves)

| Concern | MySQL family |
|---|---|
| Identifier quote | backtick `` `x` ``, internal `` ` `` doubled; keyword list = MySQL/MariaDB reserved words (new list, not PG's) |
| Placeholder | positional `?` — **note: not reusable**, so `Q::bind()` name-reuse must emit `?` per occurrence (or bind the value once per `?`) rather than reuse a numbered slot |
| String literal | `'...'`, double `''` and escape `\` → `\\` (default sql_mode) |
| Identifier validity regex | MySQL identifier rules (differs from PG — e.g. leading digits allowed, `$` allowed) |

`SqlWriter` / `InnerSqlWriter` / `QueryBuilder` / `Precedence` infrastructure is
copied from PG unchanged in shape (Precedence gets a new operator map, §7).

---

## 3. The three-dimensional clause model

Every clause is judged on three axes (recap of the agreed rubric):

1. **Compat** — supported in MySQL? MariaDB? (keep / drop / replace).
2. **Variant** — MySQL-only / MariaDB-only / both.
3. **Scope** — *should we expose it*: **include** (shapes the logical query) /
   **defer** (niche, cheap to add later) / **exclude** (hint, side-effect, or
   admin concern; reachable via `Q::func`/raw if ever needed).

Scope verdicts applied below:
- **Exclude:** `INTO OUTFILE/DUMPFILE/@var`, priority modifiers
  (`LOW_PRIORITY`/`HIGH_PRIORITY`/`DELAYED`/`QUICK`), `SQL_*` result modifiers,
  `SQL_CALC_FOUND_ROWS`, `PROCEDURE`, `ROWS EXAMINED`, `WAIT n`, `FOR PORTION OF`.
- **Defer:** `PARTITION (...)` selection, index hints, `STRAIGHT_JOIN`,
  `NATURAL JOIN`, MariaDB `OFFSET..FETCH`/`CYCLE`, `INSERT ... SET` form,
  MySQL `VALUES ROW()`/`TABLE` source forms.
- **Include:** everything that shapes the query (see per-statement tables).

---

## 4. Per-statement plans

Condensed; full clause-by-clause tables and verbatim grammar in the research
extracts. "drop" = PG-only, removed; "replace" = same intent, different SQL;
"add" = new for MySQL/MariaDB.

### SELECT

- **Keep:** `select`/`distinct`, `from`+`as`+derived-table `columnAliases`,
  `join`/`leftJoin`/`rightJoin`/`crossJoin` + `on`/`using`/`as`, `where`,
  `groupBy` (plain), `having`, named `window`, `orderBy` + `asc`/`desc`,
  `limit`/`offset`, `union`/`intersect`/`except` + `all()`/`query()`,
  `forUpdate` + `nowait`/`skipLocked`, `with`/`withRecursive`, the
  `derive()`/`isEmpty()`/`writeSelectParts()` plumbing.
- **Drop (PG-only):** `DISTINCT ON`, `fromOnly` (ONLY), `fullJoin` (no FULL
  OUTER), `groupBy` `cube`/`groupingSets`/`empty`/`distinct`, `orderBy`
  `nullsFirst`/`nullsLast`, `forNoKeyUpdate`/`forKeyShare`, `RowsFrom` /
  `WITH ORDINALITY`, CTE `[NOT] MATERIALIZED`, `SEARCH`.
- **Replace:** `forShare()` → `FOR SHARE` (MySQL) / `LOCK IN SHARE MODE`
  (MariaDB); `groupBy().rollup()` → trailing `WITH ROLLUP` (not `ROLLUP(...)`);
  `offset` always renders within `LIMIT n OFFSET m`.
- **Add (include):** `lateral` from/join family (**MySQL-only**, build-validated);
  `of(tbl)` on the lock (**MySQL-only**). `intersect`/`except` `all()` gated
  (MySQL 8.0.31 / MariaDB 10.5).
- **Defer/exclude:** leading modifiers, `PARTITION`, `STRAIGHT_JOIN`,
  `NATURAL JOIN`, MariaDB `OFFSET..FETCH`/`ROWS EXAMINED`/`WAIT`/`CYCLE`, `INTO`.

### INSERT

- **Keep:** `insertInto`, `columnNames`, `values` (repeatable), `setMap`,
  `query` (`INSERT ... SELECT`).
- **Drop:** table `as()` alias (no such thing in MySQL/MariaDB INSERT); the whole
  PG `ON CONFLICT` chain (`onConstraint`, conflict-target `where`, `doUpdate`,
  DO-UPDATE `where`); leading `WITH` on INSERT (CTE only inside the feeding
  SELECT).
- **Replace:** `defaultValues()` → render `() VALUES ()`; `onConflict(...)` /
  `OnConflict*` (two states) → a **single** `onDuplicateKeyUpdate()` →
  `OnDuplicateKeyUpdateInsertBuilder` with `set(col, val)` (no target, no WHERE).
  Reuse `UpdateSetItem`.
- **Add:** `ignore()` (`INSERT IGNORE`, the practical `DO NOTHING` analogue —
  document the semantic difference); proposed-row value reference for the upsert:
  `new.col` (MySQL, via emitted `AS new` row alias) vs `VALUES(col)` (MariaDB) —
  a variant-branched expression helper. `returning()` + `ReturningInsertBuilder`
  → **MariaDB-only** (build-validated), reuse `ReturningItem`.
- **Defer:** `INSERT ... SET`, `PARTITION`.

### REPLACE (new statement; no PG analog — mirror `InsertBuilder`)

- A small `replaceInto()` → `ReplaceBuilder`: `columnNames`, `values`, `set`/
  `setMap`, `query` (`REPLACE ... SELECT`), `Q::default()` for DEFAULT. No
  `ON DUPLICATE KEY UPDATE` (REPLACE *is* the conflict resolution), no table
  alias, no leading WITH.
- `returning()` → **MariaDB-only** (build-validated).
- Defer: `PARTITION`, MySQL `ROW()`/`TABLE` source forms.

### UPDATE

- **Keep:** `update`, target `as`, `set`/`setMap`, `where`, `applyIf`.
- **Drop:** `returning()` + `ReturningUpdateBuilder` — **no UPDATE RETURNING** in
  MySQL 8.4 or MariaDB 11.x (MariaDB only at 13.0, with `OLD_VALUE()` — out of
  anchor).
- **Replace:** PG `from()` → `FromUpdateBuilder` becomes **multi-table UPDATE**
  (`UPDATE t1 JOIN t2 ... SET ...` / comma list); qualified `SET t.col = ...`.
- **Add (include):** `orderBy`/`limit` — **single-table only** (illegal on
  multi-table; enforce). Leading `WITH` on UPDATE — MySQL yes; MariaDB only
  12.3+ → build-validated off for 11.x.
- **Defer/exclude:** `LOW_PRIORITY`/`IGNORE` modifiers, `PARTITION`,
  `FOR PORTION OF`.

### DELETE

- **Keep:** `deleteFrom`, target `as` (MariaDB ≥11.6 — build-gate), `where`.
- **Replace:** PG auxiliary `using()` → `FromDeleteBuilder` does **not** map.
  MySQL/MariaDB have two *multi-table* forms; `DELETE t.* FROM <refs>` is the
  portable one. Expose multi-table delete explicitly; PG `DELETE FROM t USING x`
  is **not valid** MySQL/MariaDB grammar.
- **Drop:** `LATERAL`/`ONLY` on the USING item.
- **Add (include):** `orderBy`/`limit` (single-table; MariaDB multi-table only
  11.8.1+ → keep single-table). `returning()` → **MariaDB single-table only**
  (build-validated; no aggregates). Leading `WITH` → MySQL yes; MariaDB 12.3+
  (off for 11.x).
- **Defer/exclude:** modifiers, `PARTITION`, `FOR PORTION OF`, index hints.

### WITH / CTE

- The MySQL-family `WithQueryItem` is a **strict subset** of PG's:
  `(recursive, name, columnNames, query)`.
- **Keep:** `with`/`withRecursive`, `as`, `columnNames`, multiple CTEs, `select`.
- **Drop:** `asMaterialized`/`asNotMaterialized`, the entire `SEARCH`
  sub-chain (`WithSearchBuilder`/`WithSearchByBuilder`/`WithQuerySearch`).
- **Gate:** `WITH` before UPDATE/DELETE — MySQL yes; MariaDB **12.3+** (so on the
  11.x anchor expose only `WITH ... SELECT`, plus INSERT/REPLACE via feeding
  SELECT).
- **Defer (MariaDB-only):** `CYCLE col RESTRICT` (the relaxed, non-standard form;
  PG builder doesn't expose CYCLE either).

---

## 5. Set operations / parenthesized query expressions

Standard query-expression grammar — already modelled correctly by the existing
`combinations` walk + `WritesParenthesizedSql`. `INTERSECT` binds tighter than
`UNION`/`EXCEPT` in both engines (matches the flat-chain rendering); per-branch
`ORDER BY`/`LIMIT` and precedence overrides come free via the nested-`query()`
parenthesised form. Port unchanged; only the version gates (`INTERSECT`/`EXCEPT`
@ MySQL 8.0.31 / MariaDB 10.3; `... ALL` @ 8.0.31 / MariaDB 10.5) and the `INTO`
tail (excluded) apply.

---

## 6. Expression / operator layer

The chokepoint. New `ExpBase` for the MySQL family. Per the dialect-native
principle (§2), only genuine **operators** are chainable `ExpBase` methods; the
"replace → function" rows below are built through the facade (`Q::func`,
`Q::cast`, `Q\Func`), not as expression methods. The table maps SQL semantics:

| PG operator | Verdict | Render (both engines unless noted) |
|---|---|---|
| `=` `<>` `<` `<=` `>` `>=` | keep | unchanged |
| `IS [NOT] NULL` | keep | unchanged |
| `IS [NOT] DISTINCT FROM` | replace | `a <=> b` / `NOT (a <=> b)` |
| `::` cast | replace | `CAST(a AS type)` — **new MySQL type validator**, not PG's |
| `\|\|` concat | replace | `CONCAT(a, b, ...)` (|| is logical OR by default) |
| `^` (pow) | replace | `POW(a, b)` (`^` is bitwise XOR here) |
| `LIKE`/`NOT LIKE` | keep | unchanged (ci by default — collation, document) |
| `ILIKE`/`SIMILAR TO` | drop | use `LIKE`/`REGEXP` + explicit `COLLATE` |
| `~`/`~*`/`!~`/`!~*` | replace | `a REGEXP b` / `a NOT REGEXP b` (case = collation; case-sensitive variant is engine-divergent → expose only the default) |
| `->` `->>` | replace | MySQL: `a -> '$.p'` / `a ->> '$.p'`. MariaDB: `JSON_EXTRACT(a,'$.p')` / `JSON_UNQUOTE(JSON_EXTRACT(...))` — **variant split** |
| `#>` `#>>` | replace/merge | fold into the `->`/`->>` renderers (single JSON-path-string model) |
| `@>` `<@` | replace | `JSON_CONTAINS(a,b)` / `JSON_CONTAINS(b,a)` |

**Add:** `nullSafeEq` (`<=>`), `regexp`/`notRegexp`, `memberOf` (`a MEMBER OF(arr)`,
MySQL ≥8.0.17 / MariaDB), `jsonValue` (`JSON_VALUE`). Optional bitwise family.

**Contract change:** JSON accessors take a **JSON path string** (`'$.k'`), not a
PG-style int/text key.

**Precedence:** a new map is required (do not reuse PG's). Deltas: `^` is bitwise
(tightest arithmetic); `||` is OR-level; `IS` sits at the comparison level;
`BETWEEN` below comparisons; an explicit `XOR` tier between `AND` and `OR`.
Upside: cast/concat/pow/json/regex move to **function form** (atomic, parens-
wrapped), shrinking the set of precedence-sensitive infix operators to
comparisons, `IS`, arithmetic, `LIKE`, `REGEXP`, `IN`, `MEMBER OF`, `<=>`, and
the boolean tiers.

---

## 7. Function facade (`Q\Func`) — curated default set

Goal: a broadly-useful default; anything omitted stays reachable via
`Q::func(name, ...)`. Mirror PG camelCase where a name already exists. Mirror the
three builder kinds: `FuncExp` (scalar), `AggBuilder` (DISTINCT/ORDER BY/...),
`WindowFuncBuilder` (`OVER`).

**Shared default (safe on both):** aggregates `count/sum/avg/min/max/groupConcat/
jsonArrayAgg/jsonObjectAgg/bitAnd/bitOr/bitXor/stddevPop/stddevSamp/varPop/
varSamp`; string `concat/concatWs/lower/upper/length/charLength/substring/left/
right/trim/ltrim/rtrim/lpad/rpad/replace/repeat/reverse/locate/instr/
substringIndex/field/findInSet/format/hex/unhex`; regexp `regexpReplace/
regexpInstr/regexpSubstr` (match via the `REGEXP` operator); numeric `abs/ceil/
floor/round/truncate/mod/power/sqrt/exp/ln/log/log2/log10/sign/rand/pi` + trig;
datetime `now/curdate/curtime/currentTimestamp/utcTimestamp/date/time/year/month/
day/hour/minute/second/quarter/week/dayOfWeek/dayOfYear/dayName/monthName/lastDay/
extract/dateAdd/dateSub/dateDiff/timestampDiff/timestampAdd/dateFormat/strToDate/
unixTimestamp/fromUnixtime/convertTz`; JSON `jsonObject/jsonArray/jsonQuote/
jsonUnquote/jsonExtract/jsonContains/jsonContainsPath/jsonKeys/jsonSearch/
jsonValue(2-arg)/jsonSet/jsonInsert/jsonReplace/jsonRemove/jsonArrayAppend/
jsonArrayInsert/jsonMergePatch/jsonMergePreserve/jsonType/jsonDepth/jsonLength/
jsonValid`; window `rowNumber/rank/denseRank/percentRank/cumeDist/ntile/lag/lead/
firstValue/lastValue/nthValue`; misc `uuid/uuidToBin/binToUuid/isUuid`.

On `Q` (not `Q\Func`), mirroring PG: `coalesce`, `nullif`, `greatest`, `least`,
`cast`/`convert`. `if`/`ifnull` on `Q\Func` (no PG analog).

**MySQL-only (gate):** `regexpLike`, `grouping`, `anyValue`, `jsonSchemaValid*`,
`jsonStorage*`, `jsonPretty`, `randomBytes`.
**MariaDB-only (gate):** `jsonQuery`, `jsonDetailed` (pretty-print), `jsonExists`,
`median`, `percentileCont`, `percentileDisc`, Oracle-compat (`toChar`,
`addMonths`, `monthsBetween`, `chr`, `oct`).

Builders that need special shapes: `groupConcat` (DISTINCT/ORDER BY/SEPARATOR),
`extract` (mirror PG `ExtractExp`), the `INTERVAL expr unit` keyword arg for
`dateAdd`/`dateSub`, `cast` (typed), `trim` (BOTH/LEADING/TRAILING ... FROM).

---

## 8. MySQL-vs-MariaDB split matrix (the build-validated / branched set)

| Feature | MySQL 8.4 | MariaDB 11.x | Handling |
|---|---|---|---|
| Identifier quote / placeholder / escaping | backtick / `?` / `\` | identical | shared — no split |
| `LATERAL` | yes (8.0.14) | **no** | availability (MySQL-only) |
| Shared lock | `FOR SHARE` (+`OF tbl`) | `LOCK IN SHARE MODE` | render-branch; `of()` MySQL-only |
| `NOWAIT`/`SKIP LOCKED` | 8.0 | 10.6 | keep (both in anchor) |
| `INTERSECT`/`EXCEPT` | 8.0.31 | 10.3 | keep; `ALL` @ 8.0.31 / 10.5 |
| `RETURNING` INSERT/REPLACE/DELETE | **no** | yes (10.5 / 10.5 / 10.0.5) | availability (MariaDB-only) |
| `RETURNING` UPDATE | no | no (13.0) | drop for both |
| `WITH` before UPDATE/DELETE | yes | **12.3** | availability (MySQL-only in anchor) |
| Upsert value-ref | `new.col` (`AS new`) | `VALUES(col)` | render-branch |
| `ROLLUP` form | `ROLLUP(...)` or `WITH ROLLUP` | `WITH ROLLUP` only | render trailing `WITH ROLLUP` for both |
| JSON `->`/`->>` operator | yes | **no** (function form) | render-branch |
| `JSON_PRETTY` / `JSON_DETAILED` | `JSON_PRETTY` | `JSON_DETAILED` | render-branch (if exposed) |
| `MEDIAN`/`PERCENTILE_*` window | no | yes | availability (MariaDB-only) |
| `GROUPING`/`ANY_VALUE`/`REGEXP_LIKE` | yes | no | availability (MySQL-only) |
| `CYCLE` on recursive CTE | no | 10.5.2 (relaxed) | availability (MariaDB-only, deferred) |

> Handling per §2: an *availability* split = the method exists only on that
> engine's builder (type-level, no runtime check); a *render-branch* = one method
> name, distinct per-class `writeSql`. No runtime variant flag.

---

## 9. Implementation staging

Goal: **PG-comparable scope** (same breadth, dialect-appropriate — PG-only
features dropped, MySQL/MariaDB-only added), incrementally, each stage green on
`vendor/bin/pest` + `vendor/bin/phpstan analyse` before the next.

**Test corpus per stage** (since there's no upstream suite to port like qrb was):
the matching PostgreSQL test cases adapted to MySQL/MariaDB SQL, **plus** verbatim
example queries from the official docs — both asserted via `toRenderSql`. Doc
examples are part of every stage, not deferred.

**Definition of done (per statement stage):** every production in that
statement's official-doc grammar carries an explicit verdict — Supported /
Deferred / Excluded — worked top-to-bottom from the doc. Deferred/Excluded
entries are recorded in §12 with a reason. Nothing is left unsupported silently.

| # | Stage | Contents |
|---|---|---|
| 0 | **Testing infra** | dialect-aware `toRenderSql` (build through the MySQL/MariaDB `QueryBuilder`, not the PG one); `tests/MySQL/` + `tests/MariaDB/` Pest bootstraps |
| 1 | **Foundation** | MySQL `SqlBuilder` (backtick / `?` / `\`+`''` escaping), `Keywords` (MySQL reserved list + backtick quoting), `Literals`, `IdentExp` (MySQL validity regex); copied machinery (`SqlWriter`/`InnerSqlWriter`/`QueryBuilder`/`Precedence`/`WritesParenthesizedSql`/structural value objects); `MySQL\Q` facade skeleton |
| 2 | **Expression layer** | function-form `ExpBase` + new `Precedence` map; `OpExp`/`FuncExp`; literals; args/binds (non-reusable `?`); `CAST` + a new MySQL type validator (replaces `TypeExp`); `CASE`; junctions (AND/OR/NOT); `IN`/`EXISTS`/`ANY`/`ALL` + subqueries; drop `ARRAY`, reshape `INTERVAL` |
| 3 | **SELECT** | clause ladder (abstract base + MySQL concretes) minus dropped clauses; includes CTEs (minus `MATERIALIZED`/`SEARCH`), set operations, locking (`FOR SHARE`); MySQL `lateral`/`of()` |
| 4 | **Window functions** | `OVER`, named `WINDOW` clause, frame clauses (`ROWS`/`RANGE BETWEEN`), window-only funcs (`row_number`/`rank`/`lag`/`lead`/`ntile`/`first_value`/…) |
| 5 | **DML** | INSERT (+ `onDuplicateKeyUpdate`, `ignore`), REPLACE, UPDATE (multi-table + order/limit), DELETE (multi-table + order/limit) — MySQL ladder |
| 6 | **Function facade** | curated `Q\Func` default set + special-shape builders (`GROUP_CONCAT`, `EXTRACT`, `INTERVAL` arg, `TRIM`) |
| 7 | **MariaDB variant** | the MariaDB concrete ladder over the shared abstract bases: `RETURNING` subclasses (INSERT/REPLACE/DELETE), `LOCK IN SHARE MODE`, no `lateral`/`of()`, MariaDB-only funcs (`JSON_QUERY`/`JSON_DETAILED`/`MEDIAN`/`PERCENTILE_*`), `MariaDB\Q` facade, `WITH`-before-UPDATE/DELETE gated off |
| 8 | **Doc-example & parity audit** | consolidated verbatim doc-example tests; a PG-parity map (each PG test → ported / adapted / dropped-with-reason); README / usage docs |

The abstract base builders are designed bi-dialect-aware from stage 1 (per the
§8 split matrix), so the MariaDB stage adds only divergent subclasses rather than
forcing base rework.

---

## 10. Resolved decisions

- **Variant modelling:** per-variant subclasses (compile-time safe) — see §2.
  No runtime variant flag; class identity carries the dialect.
- **Naming:** `src/MySQL/` holds the shared primitives + machinery + abstract
  base builders + the MySQL ladder; `src/MariaDB/` holds the MariaDB facade and
  its divergent builder subclasses, reusing `src/MySQL/Builder/`. Facades
  `MySQL\Q` / `MySQL\Q\Func` and `MariaDB\Q` / `MariaDB\Q\Func`, peers of
  `PostgreSQL\Q`.

Open sub-decision deferred to the SELECT stage: how far the MySQL-only `lateral`
methods thread down the transition chain.

## 11. Sources

Official MySQL 8.4 reference manual and MariaDB KB/docs, per-area URLs in the
research extracts. Anchors confirmed by re-fetch where divergence mattered
(verify pass); the REPLACE verify stage failed on output formatting only — its
research file is complete and was cross-checked against the INSERT findings.

---

## 12. Coverage ledger — deliberate non-support

Principle: implement each area **against the full official-doc grammar** and give
**every production** an explicit verdict. "Not supported" is always a recorded
decision, never a silent omission. Statuses:

- **Supported** — built + tested (the default; see §4/§6/§7 + the test corpus).
- **Deferred** — in scope eventually, not this pass; cheap to add later behind an
  explicit method. (The "(yet)" cases.)
- **Excluded** — out of scope: not query-shape (result routing, optimizer/priority
  hints, server/session knobs), deprecated, or stored-program-only. Reachable via
  raw SQL / `Q::func` if ever needed.
- **N/A (PG-only)** — the PG builder has it; MySQL/MariaDB don't (see §4/§6).

Deferred + Excluded surface in the dialect README "Limitations" section (stage 8).
`SELECT ... INTO` is the canonical Excluded case, in both senses:
`INTO OUTFILE/DUMPFILE/@var` (result routing) and stored-program `INTO var_list`
(variable assignment).

### SELECT
| Clause | Status | Reason |
|---|---|---|
| `INTO OUTFILE/DUMPFILE/@var`, stored-program `INTO var_list` | Excluded | result routing / variable assignment — not query shape |
| `HIGH_PRIORITY`, `SQL_SMALL/BIG/BUFFER_RESULT`, `SQL_CACHE`/`SQL_NO_CACHE`, `SQL_CALC_FOUND_ROWS` | Excluded | optimizer/result hints; `SQL_CALC_FOUND_ROWS` deprecated |
| `PROCEDURE name(...)` | Excluded | deprecated, outside the query model |
| `ROWS EXAMINED n`, `WAIT n` lock option (MariaDB) | Excluded | resource / lock-timeout knobs |
| `PARTITION (...)` selection | Deferred | partition pruning; from-item add later |
| index hints (`USE`/`FORCE`/`IGNORE INDEX`) | Deferred | optimizer hint |
| `STRAIGHT_JOIN`, `NATURAL [LEFT\|RIGHT] JOIN` | Deferred | niche join forms |
| `LIMIT offset, count` short form | Deferred | `LIMIT n OFFSET m` covers it |
| MariaDB `OFFSET..FETCH FIRST/NEXT ... ONLY/WITH TIES` | Deferred | LIMIT/OFFSET covers the common case |
| MariaDB recursive-CTE `CYCLE col RESTRICT` | Deferred | PG builder exposes no CYCLE either |

### Window functions

The `over_clause` (`OVER (window_spec)` and `OVER window_name`), the named `WINDOW`
clause, `PARTITION BY`, the window `ORDER BY`, and the `frame_clause`
(`ROWS`/`RANGE` with `CURRENT ROW` / `UNBOUNDED PRECEDING|FOLLOWING` /
`expr PRECEDING|FOLLOWING` bounds, single-bound and `BETWEEN … AND …` forms) are
all **Supported**. The window-only functions `ROW_NUMBER`, `RANK`, `DENSE_RANK`,
`PERCENT_RANK`, `CUME_DIST`, `NTILE`, `LAG`, `LEAD`, `FIRST_VALUE`, `LAST_VALUE`,
`NTH_VALUE` are Supported via `Q\Func`.

| Production | Status | Reason |
|---|---|---|
| `GROUPS` frame units | N/A (not in grammar) | only `ROWS`/`RANGE` exist in MySQL 8.4 / MariaDB 11.x |
| frame `EXCLUDE CURRENT ROW`/`GROUP`/`TIES`/`NO OTHERS` | N/A (not in grammar) | frame exclusion is unsupported by both engines |
| aggregate-as-window beyond `count`/`sum`/`avg`/`min`/`max` | Deferred | the full curated aggregate set (and its `over()`) lands with the function facade (§7, stage 6) |

### INSERT / REPLACE

INSERT (`columnNames`/`values`/`setMap`/`query`/`defaultValues`→`() VALUES ()`,
`IGNORE`, `ON DUPLICATE KEY UPDATE` with the `AS new` row alias reached via
`Q::inserted('col')`) and REPLACE (same source forms, no `ON DUPLICATE KEY UPDATE`)
are **Supported**. `RETURNING` on INSERT/REPLACE is MariaDB-only (stage 7); absent
on MySQL.

| Clause | Status | Reason |
|---|---|---|
| `LOW_PRIORITY`/`HIGH_PRIORITY`/`DELAYED` | Excluded | priority hints; `DELAYED` ignored in 8.4 |
| `INSERT ... SET` / `REPLACE ... SET` assignment form | Deferred | `(cols) VALUES (...)` covers it |
| `PARTITION (...)` | Deferred | partition selection |
| MySQL `VALUES ROW(...)` / `TABLE tbl` source | Deferred | MySQL-only source forms |
| INSERT/REPLACE `RETURNING` | N/A (MariaDB-only) | availability split — added with the MariaDB ladder (stage 7) |

### UPDATE / DELETE

Single-table UPDATE/DELETE (`set`/`setMap`, `where`, `orderBy`+`asc`/`desc`,
`limit`, target `as`), multi-table UPDATE/DELETE via `join`/`leftJoin`/`rightJoin`/
`crossJoin` (DELETE renders the portable `DELETE tbl.* FROM <refs>` form), and a
leading `WITH` are **Supported**. `ORDER BY`/`LIMIT` are build-validated off for
multi-table statements. `UPDATE ... RETURNING` is dropped for both engines (only
MariaDB 13+); `DELETE ... RETURNING` is MariaDB-only (stage 7).

| Clause | Status | Reason |
|---|---|---|
| `LOW_PRIORITY`/`QUICK` | Excluded | priority hints |
| `IGNORE` | Deferred | error-demotion toggle; add as a modifier later |
| `PARTITION (...)` | Deferred | partition selection |
| comma-separated table list (`UPDATE a,b` / `DELETE a,b FROM`) | Deferred | the `JOIN` form (incl. `CROSS JOIN`) covers it |
| multi-target DELETE (`DELETE t1,t2 FROM …`) | Deferred | single-target `DELETE t.* FROM <refs>` is the portable form |
| `DELETE FROM t USING <refs>` form | Deferred | the `DELETE t.* FROM <refs>` form is rendered instead |
| `UPDATE ... RETURNING` | N/A | no engine in anchor (MariaDB 13+ only) |
| `DELETE ... RETURNING` | N/A (MariaDB-only) | availability split — added with the MariaDB ladder (stage 7) |
| MariaDB `FOR PORTION OF period FROM..TO` | Excluded | application-time temporal tables, separate feature |
| MariaDB DELETE index hints (11.8.1+) | Deferred | optimizer hint |

### Expressions
| Item | Status | Reason |
|---|---|---|
| bitwise `&` `\|` `^`(XOR) `<<` `>>` `~` | Deferred | not in the PG surface; add if wanted |
| case-sensitive regex (`~`/`!~` equivalents) | Deferred | engine-divergent (`REGEXP_LIKE(...,'c')` vs `REGEXP BINARY`); only the ci-default `REGEXP` ships now |
| JSON `->`/`->>` operators on MariaDB expressions | MySQL-native (shared base) | MariaDB lacks these operators (MDEV-13594); they live on the shared `ExpBase` as MySQL syntax — MariaDB code should use `Q\Func::jsonExtract`/`jsonUnquote`. A full expression-layer fork to remove two methods from MariaDB is deliberately deferred as disproportionate. |
| `ILIKE`, `SIMILAR TO`, `::`, `\|\|`, `^`(pow), `@>`/`<@`, `#>`/`#>>`, `ARRAY` | N/A (PG-only) | dropped or mapped to functions — see §6 |

### Functions

The curated `Q\Func` default set (§7) is **Supported**: the aggregates
(`count`/`sum`/`avg`/`min`/`max`/`groupConcat`/`jsonArrayAgg`/`jsonObjectAgg`/
`bitAnd`/`bitOr`/`bitXor`/`stddevPop`/`stddevSamp`/`varPop`/`varSamp`, each usable
with `OVER`), the string/regexp/numeric/date-time/JSON/misc scalar families, the
window functions (stage 4), and the special-shape builders (`GROUP_CONCAT`,
`EXTRACT`, `INTERVAL`, `TRIM`, `CAST`/`CONVERT`). On `Q`: `coalesce`/`nullif`/
`greatest`/`least`/`cast`/`convert`/`interval`; on `Q\Func`: `if`/`ifnull`.
Anything omitted stays reachable via `Q::func(name, ...)`.

| Category / function | Status | Reason |
|---|---|---|
| Spatial / GIS (`ST_*`, `MBR*`) | Excluded | large specialized surface; via `Q::func` |
| Encryption / compression (`AES_*`, `MD5`/`SHA*`, `COMPRESS`) | Excluded from default | security/config-sensitive; via `Q::func` |
| Locking / information / replication / internal funcs | Excluded | connection/server state, not query shape |
| XML (`ExtractValue`, `UpdateXML`) | Excluded | niche |
| `JSON_TABLE` | Deferred | FROM-clause table function (FROM machinery, not `Q\Func`) |
| `MEMBER OF` operator | Deferred | JSON membership; add to the expression layer if wanted |
| MySQL-only `REGEXP_LIKE`/`GROUPING`/`ANY_VALUE`/`JSON_SCHEMA*`/`JSON_STORAGE*`/`JSON_PRETTY`/`RANDOM_BYTES` | Supported (MySQL facade only) | gated; absent on MariaDB |
| MariaDB-only `JSON_QUERY`/`JSON_DETAILED`/`JSON_EXISTS`/`MEDIAN`/Oracle-compat (`TO_CHAR`/`ADD_MONTHS`/`MONTHS_BETWEEN`/`CHR`/`OCT`) | Supported (MariaDB facade only) | gated; absent on MySQL |
| MariaDB `PERCENTILE_CONT`/`PERCENTILE_DISC` | Deferred | ordered-set aggregates with `WITHIN GROUP (ORDER BY …)` — a distinct builder shape; via `Q::func` until added |
