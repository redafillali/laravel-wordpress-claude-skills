# Index patterns & query rewrites

## Covering index

```sql
-- Hot query
SELECT user_id, total FROM orders WHERE status = 'paid' AND created_at >= '2026-01-01';
-- Covering: all referenced columns in the index → "Using index", no table lookups
ALTER TABLE orders ADD INDEX idx_status_created_cover (status, created_at, user_id, total);
```

InnoDB secondary indexes implicitly include the primary key.

## ORDER BY + LIMIT served by index

```sql
SELECT * FROM posts WHERE category_id = 7 ORDER BY published_at DESC LIMIT 10;
ALTER TABLE posts ADD INDEX idx_cat_pub (category_id, published_at);
-- MySQL reads 10 index entries backwards, no filesort.
```

Mixed directions (`ORDER BY a ASC, b DESC`) need a descending index (MySQL 8): `INDEX (a, b DESC)`.

## GROUP BY

```sql
SELECT customer_id, COUNT(*) FROM orders WHERE status='paid' GROUP BY customer_id;
INDEX (status, customer_id)   -- avoids Using temporary
```

## Generated column for functional filters

```sql
ALTER TABLE users
  ADD email_lower VARCHAR(255) GENERATED ALWAYS AS (LOWER(email)) STORED,
  ADD INDEX idx_email_lower (email_lower);
-- MySQL 8.0.13+: functional index directly
ALTER TABLE users ADD INDEX idx_email_lower ((LOWER(email)));
```

JSON attribute:

```sql
ALTER TABLE products ADD brand VARCHAR(100) GENERATED ALWAYS AS (attributes->>'$.brand') VIRTUAL, ADD INDEX (brand);
```

Laravel: `$table->string('brand')->virtualAs("attributes->>'$.brand'")->index();`

## Keyset pagination

```sql
-- page 1
SELECT id, title FROM posts ORDER BY id DESC LIMIT 20;
-- next pages: pass last id
SELECT id, title FROM posts WHERE id < :last_id ORDER BY id DESC LIMIT 20;
```

Laravel: `cursorPaginate()`.

## OR → UNION

```sql
SELECT * FROM leads WHERE phone = ? OR email = ?;
-- each column indexed separately; index_merge may or may not kick in. Explicit:
SELECT * FROM leads WHERE phone = ?
UNION
SELECT * FROM leads WHERE email = ?;
```

## Search

- `LIKE 'abc%'` can use an index; `LIKE '%abc%'` cannot.
- `FULLTEXT(title, body)` + `MATCH ... AGAINST` (natural language or boolean mode); minimum token size `innodb_ft_min_token_size` (default 3).
- Heavy search → Meilisearch/Typesense/Elasticsearch via Laravel Scout.

## Joins

- Index the join column on the joined (inner) table.
- Same data type and collation on both sides (utf8mb4 vs utf8mb3 mismatch prevents index use).
- Filter early; avoid joining to fetch columns you don't select.

## Data types

- Use the smallest correct type: `INT UNSIGNED` vs `BIGINT`, `TINYINT` for flags, `DECIMAL(10,2)` for money (never FLOAT), `DATETIME`/`TIMESTAMP` not strings, `ENUM`/lookup tables vs repeated long strings.
- `utf8mb4` everywhere; `VARCHAR(191)` only needed for old MySQL 5.6 index limits.
- Large TEXT/JSON columns in a side table if rarely read.

## Maintenance

```sql
ANALYZE TABLE posts;                 -- refresh statistics if plans look wrong
OPTIMIZE TABLE posts;                -- reclaim space after big deletes (rebuilds table; locks on older versions)
SELECT * FROM sys.schema_unused_indexes;
SELECT * FROM sys.schema_redundant_indexes;
SELECT table_name, ROUND((data_length+index_length)/1024/1024) mb, table_rows
FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY mb DESC LIMIT 20;
```
