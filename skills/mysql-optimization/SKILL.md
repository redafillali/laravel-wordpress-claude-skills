---
name: mysql-optimization
description: Find and fix slow MySQL/MariaDB queries and schemas behind Laravel and WordPress sites — slow query log, EXPLAIN / EXPLAIN ANALYZE, index design (composite, covering, prefix), query rewrites, pagination, schema and data-type choices, wp_postmeta and wp_options bloat, InnoDB configuration, locking/deadlocks, and safe online migrations on large tables. Use this skill whenever the user shares a slow SQL query or EXPLAIN output, complains that the database or MySQL CPU is high, asks which index to add, sees deadlocks or "Lock wait timeout", has a huge table to alter, or wants to optimize a Laravel/WordPress database — even if they only say "the site is slow and I think it's the DB".
---

# MySQL Optimization (Laravel & WordPress)

The database is usually the first bottleneck of a PHP site. Optimize with evidence: find the queries that consume the most **total** time (frequency × duration), make them use an index that matches their shape, and only then touch server settings.

## 1. Find the expensive queries

```sql
-- Turn on the slow log (runtime; persist in my.cnf)
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 0.5;            -- seconds
SET GLOBAL log_queries_not_using_indexes = 0; -- noisy; enable briefly if needed
```

Summarize: `pt-query-digest /var/log/mysql/slow.log | head -100` (Percona Toolkit) or `mysqldumpslow -s t -t 20 slow.log`.

Without file access (MySQL 8 / MariaDB with performance_schema):

```sql
SELECT DIGEST_TEXT, COUNT_STAR calls,
       ROUND(SUM_TIMER_WAIT/1e12,2) total_s,
       ROUND(AVG_TIMER_WAIT/1e9,1) avg_ms,
       SUM_ROWS_EXAMINED rows_examined, SUM_ROWS_SENT rows_sent
FROM performance_schema.events_statements_summary_by_digest
ORDER BY SUM_TIMER_WAIT DESC LIMIT 15;
```

A query examining 100× more rows than it returns is the classic missing-index signal.

## 2. Read the plan

`EXPLAIN` (estimate) and on MySQL 8.0.18+ `EXPLAIN ANALYZE` (actually runs it — careful on writes/prod).

| Column | Look for |
|---|---|
| `type` | `ALL` (full scan) / `index` (full index scan) on big tables = problem; `ref`, `range`, `eq_ref`, `const` = good |
| `key` | NULL = no index used |
| `rows` × `filtered` | rows actually processed |
| `Extra` | `Using filesort`, `Using temporary` on large sets = sort/group not served by index; `Using index` = covering (great) |

## 3. Design the index from the query

Rule of thumb for a composite index: **equality columns first → then one range or ORDER BY column → then extra selected columns to make it covering.**

```sql
-- Query
SELECT id, title, created_at FROM posts
WHERE tenant_id = ? AND status = 'published' AND created_at >= ?
ORDER BY created_at DESC LIMIT 20;

-- Index
ALTER TABLE posts ADD INDEX idx_tenant_status_created (tenant_id, status, created_at);
```

- Leftmost prefix: `(a, b, c)` serves `a`, `a,b`, `a,b,c` — not `b` alone.
- After a range condition, later columns can't be used for filtering in the same way.
- Don't index every column; each index slows writes and costs RAM. Remove unused/duplicate ones (`sys.schema_unused_indexes`, `sys.schema_redundant_indexes`).
- Low-cardinality columns alone (`status`, `is_active`) rarely help — combine them.
- Long strings: prefix index `(email(50))` or a hash column; full-text search → `FULLTEXT` index, not `LIKE '%x%'`.

More patterns and rewrites: [references/indexes-and-rewrites.md](references/indexes-and-rewrites.md).

## 4. Rewrite queries that can't use indexes

- Functions on columns (`DATE(created_at) = ?`, `LOWER(email) = ?`) → range conditions or generated columns with an index.
- Implicit type conversion (`varchar_col = 123`) → compare with the right type.
- `OR` across different columns → `UNION ALL` of two indexed queries, or a better composite index.
- `OFFSET` deep pagination → keyset (`WHERE id < ? ORDER BY id DESC LIMIT 20`).
- `SELECT *` on wide rows (TEXT/JSON) → select needed columns; allows covering indexes.
- `NOT IN (subquery)` with NULLs → `NOT EXISTS`.
- `COUNT(*)` on huge tables for every page view → cached or approximate counts.

## 5. WordPress-specific

`wp_postmeta` and `wp_options` are the usual suspects. See [references/wordpress-database.md](references/wordpress-database.md): autoload bloat, orphaned meta, transients, revisions, meta_query performance, and the useful extra index on `wp_postmeta(meta_key, meta_value(32))`.

## 6. Server configuration (after queries are fixed)

Key InnoDB settings (dedicated DB server): `innodb_buffer_pool_size` ≈ 60–70 % of RAM (on a shared web+DB VPS, size it so the working set fits without swapping PHP), `innodb_log_file_size`/`innodb_redo_log_capacity` large enough for write bursts, `innodb_flush_log_at_trx_commit` (1 = safest), `max_connections` matched to PHP-FPM workers, `tmp_table_size`/`max_heap_table_size` for GROUP BY. Check buffer pool hit rate and swap first. Details: [references/server-config.md](references/server-config.md).

## 7. Large-table changes and locking

- `ALTER TABLE` on millions of rows: use `ALGORITHM=INPLACE, LOCK=NONE` when supported, or `pt-online-schema-change` / `gh-ost`. In Laravel, run such migrations separately from the deploy.
- Deadlocks / lock waits: keep transactions short, access rows in a consistent order, index the columns in `UPDATE ... WHERE` (otherwise InnoDB locks many rows), retry on deadlock (`DB::transaction($fn, attempts: 3)`). Inspect with `SHOW ENGINE INNODB STATUS\G`.
- Batch big deletes/updates: `DELETE ... WHERE ... LIMIT 5000` in a loop.

## 8. How to answer

1. Ask for (or work from) the query, its EXPLAIN, table sizes (`SHOW TABLE STATUS` / `information_schema.TABLES`), and existing indexes (`SHOW INDEX FROM t`).
2. Explain what the plan shows in one or two lines.
3. Give the exact `ALTER TABLE` / Laravel migration (`$table->index([...], 'name')`) and/or rewritten query.
4. Say how to verify (new EXPLAIN: `type`, `rows`, `Extra`; timing before/after).
5. Warn about write cost, lock impact of the ALTER on large tables, and test on a copy first.
