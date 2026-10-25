# Eloquent performance & database patterns

## Contents
1. Detecting problems
2. N+1 and eager loading
3. Aggregates without loading relations
4. Large datasets
5. Indexing
6. Common traps

## 1. Detecting problems

- Dev: `Model::preventLazyLoading(! app()->isProduction());` in `AppServiceProvider::boot()`.
- Also useful: `Model::preventSilentlyDiscardingAttributes()` and `Model::preventAccessingMissingAttributes()` (or `Model::shouldBeStrict()` to enable all three).
- Laravel Debugbar / Telescope / Pulse to see query count and duration.
- `DB::listen(fn ($q) => logger($q->sql, $q->bindings));` for a quick trace.
- `->toRawSql()` (Laravel 10.15+) or `->toSql()` to inspect a builder.
- `EXPLAIN` the slow query in MySQL/PostgreSQL.

## 2. N+1 and eager loading

```php
// ❌ 1 + N queries
$posts = Post::all();
foreach ($posts as $post) { echo $post->author->name; }

// ✅ 2 queries
$posts = Post::with('author:id,name')->get();
```

- Nested: `with('comments.author')`. Constrained: `with(['comments' => fn ($q) => $q->latest()->limit(5)])`.
- Already loaded collection: `$posts->load('tags')`; only if missing: `loadMissing()`.
- Default eager load on a model: `protected $with = ['author'];` — use sparingly, it applies everywhere.
- API Resources: use `$this->whenLoaded('author')` so resources don't trigger lazy loads.

## 3. Aggregates without loading relations

```php
Post::withCount('comments')->withSum('orders', 'total')->get();
// $post->comments_count, $post->orders_sum_total

User::whereHas('orders', fn ($q) => $q->where('status', 'paid'))->get();
// For large tables, whereExists/join can be faster than whereHas — check EXPLAIN.
```

Latest related row: `$user->latestOrder()` defined as `hasOne(Order::class)->latestOfMany()`.

## 4. Large datasets

| Need | Use |
|---|---|
| Process all rows with updates | `chunkById(500, fn ($rows) => ...)` (safe when modifying the filtered column) |
| Stream rows, low memory | `lazyById()` or `cursor()` (cursor can't eager load) |
| Bulk insert | `Model::insert([...])` or `upsert()` — skips events/timestamps, set them manually |
| Bulk update | `Model::where(...)->update([...])` — one query, no events |
| Export | `lazyById()` + streamed response or a queued job |

Never `Model::all()` on a growing table; never paginate with `offset` on huge tables when `cursorPaginate()` works.

## 5. Indexing

- Index foreign keys (`foreignId()->constrained()` does it) and columns used in `WHERE`, `ORDER BY`, `GROUP BY`.
- Composite index order: equality columns first, then range/sort: `$table->index(['status', 'created_at']);`.
- Unique constraints in the DB, not only in validation (race conditions).
- Avoid functions on indexed columns in WHERE (`whereDate('created_at', ...)` can prevent index use — prefer `whereBetween('created_at', [$start, $end])`).
- Leading-wildcard `LIKE '%foo'` cannot use a B-tree index; consider full-text (`$table->fullText('body')` + `whereFullText`) or Scout.

## 6. Common traps

- `$model->relation` (property) vs `$model->relation()` (builder) — calling `->count()` on the property loads the whole collection.
- `->get()->count()` instead of `->count()`.
- `orWhere` without grouping breaks scoping: wrap in `where(fn ($q) => $q->where(...)->orWhere(...))`.
- Accessors that query the DB inside loops.
- `firstOrCreate` races under concurrency — back it with a unique index and catch `UniqueConstraintViolationException` (Laravel 10.29+) or use `createOrFirst()`.
- Model events (`saving`, observers) don't fire on mass `update()`/`insert()`.
- Soft deletes: unique indexes must account for `deleted_at`.
