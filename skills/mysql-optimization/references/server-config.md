# MySQL / MariaDB server configuration

Change settings one at a time, measure, and keep a copy of the original `my.cnf`. Defaults on small VPS images are often far from optimal, but query/index fixes almost always matter more.

## Starting point (MySQL 8, InnoDB)

```ini
[mysqld]
# Memory
innodb_buffer_pool_size = 2G          # dedicated DB: 60–70% of RAM; shared with PHP: leave room for FPM + Redis
innodb_buffer_pool_instances = 2      # ~1 per GB, up to 8

# Redo log / durability
innodb_redo_log_capacity = 1G         # MySQL 8.0.30+; older: innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 1    # 2 = faster, may lose ~1s of transactions on OS crash
innodb_flush_method = O_DIRECT

# Connections & temp tables
max_connections = 200                 # ≥ total PHP-FPM max_children across sites + workers + margin
tmp_table_size = 64M
max_heap_table_size = 64M

# Logging
slow_query_log = 1
long_query_time = 0.5
slow_query_log_file = /var/log/mysql/slow.log
```

Do **not** enable the query cache (removed in MySQL 8; on MariaDB it often hurts under write load).

## Health queries

```sql
-- Buffer pool hit ratio (want > 99%)
SELECT ROUND(100 * (1 - (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Innodb_buffer_pool_reads')
                      / (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Innodb_buffer_pool_read_requests')), 2) AS hit_pct;

-- Temp tables going to disk
SHOW GLOBAL STATUS LIKE 'Created_tmp%';

-- Connections
SHOW GLOBAL STATUS LIKE 'Max_used_connections';
SHOW PROCESSLIST;
```

Tools: `mysqltuner.pl` (sanity check, don't apply blindly), Percona Monitoring and Management, `htop`/`iostat` to see whether the box is CPU, RAM (swap!) or I/O bound.

## Shared VPS (web + DB on one box)

Typical 4 GB VPS: buffer pool 1–1.5 G, PHP-FPM ~1.5 G, Redis 256 M, OS/cache the rest. Swap usage is the first thing to eliminate — a swapping MySQL is slower than any missing index.

## Backups

- `mysqldump --single-transaction --quick --routines` (consistent for InnoDB without locking), or `mydumper` for big DBs.
- Test restores regularly; keep off-server copies.
