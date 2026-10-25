# Database & Eloquent performance patterns

## Before / after catalogue

```php
// N+1 in a Blade loop
@foreach(Order::latest()->paginate(50) as $order) {{ $order->customer->name }} {{ $order->items->count() }}
// →
$orders = Order::with('customer:id,name')->withCount('items')->latest()->paginate(50);
// view: {{ $order->items_count }}
```

```php
// Counting through a loaded relation
$user->posts->count();          // loads all posts
$user->posts()->count();        // COUNT(*) query
```

```php
// Loop of updates
foreach ($ids as $id) { Product::find($id)->update(['active' => false]); }   // 2N queries
Product::whereIn('id', $ids)->update(['active' => false]);                    // 1 query
```

```php
// Upsert import
Product::upsert($rows, uniqueBy: ['sku'], update: ['price', 'stock', 'updated_at']);
```

```php
// Expensive whereHas on big tables
User::whereHas('orders', fn ($q) => $q->where('status', 'paid'))->get();
// Alternative: whereIn subquery / join, then compare EXPLAIN
User::whereIn('id', Order::select('user_id')->where('status', 'paid'))->get();
```

```php
// Deep offset pagination
Post::orderBy('id')->paginate(50, page: 4000);   // OFFSET 199950 scans rows
Post::orderBy('id')->cursorPaginate(50);         // WHERE id > ? LIMIT 50
```

```php
// Date filters that break indexes
->whereDate('created_at', $day)                   // DATE(created_at) = ? → no index
->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()])
```

## Indexes for common Laravel queries

| Query shape | Index |
|---|---|
| `where('tenant_id', ?)->orderByDesc('created_at')` | `(tenant_id, created_at)` |
| `where('status', ?)->where('user_id', ?)` | `(user_id, status)` (most selective first if both equality) |
| polymorphic `morphTo` | `(xxx_type, xxx_id)` — `morphs()` creates it |
| pivot `belongsToMany` | composite primary/unique `(a_id, b_id)` + index `(b_id)` |
| soft deletes heavy tables | add `deleted_at` to composite indexes used with the default scope |
| `whereJsonContains` / JSON paths | generated column + index |

## Reading EXPLAIN quickly

- `type`: `ALL` = full scan (bad on big tables), `ref`/`range`/`eq_ref`/`const` = index used.
- `rows`: estimated rows examined — compare with rows returned.
- `Extra`: `Using filesort` / `Using temporary` on large sets → index the ORDER BY / GROUP BY columns.
- `key`: which index was chosen (NULL = none).

Laravel: `Post::where(...)->explain()->dd();`

## Counting & dashboards

- Dashboards with many aggregates → one query with conditional sums:
  ```php
  Order::selectRaw("COUNT(*) total, SUM(status = 'paid') paid, SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) revenue")->first();
  ```
- Precompute heavy stats in a scheduled job into a summary table; read the summary.
- Exact `COUNT(*)` on huge InnoDB tables is slow — cache it or use `simplePaginate()` (no total).
