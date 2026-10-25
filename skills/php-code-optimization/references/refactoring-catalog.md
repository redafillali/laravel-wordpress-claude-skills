# Refactoring catalogue (PHP 8.x / Laravel / WordPress)

## Deep nesting → guard clauses

```php
// Before
function discount(?User $user, Order $order): float {
    if ($user) {
        if ($user->isActive()) {
            if ($order->total > 100) { return 0.1; } else { return 0.05; }
        } else { return 0; }
    }
    return 0;
}
// After
function discount(?User $user, Order $order): float {
    if (! $user?->isActive()) {
        return 0.0;
    }
    return $order->total > 100 ? 0.1 : 0.05;
}
```

## Nested loops O(n·m) → keyed lookup O(n+m)

```php
// Before
foreach ($orders as $order) {
    foreach ($customers as $customer) {
        if ($customer['id'] === $order['customer_id']) { $order['customer'] = $customer; }
    }
}
// After
$customersById = array_column($customers, null, 'id');   // or collect($customers)->keyBy('id')
foreach ($orders as &$order) {
    $order['customer'] = $customersById[$order['customer_id']] ?? null;
}
unset($order);
```

## array_merge in a loop → append / spread once

```php
// Before: O(n²) copying
$all = [];
foreach ($pages as $page) { $all = array_merge($all, $page->items()); }
// After
$all = array_merge(...array_map(fn ($p) => $p->items(), $pages));
```

## Filtering in PHP → in the database

```php
// Before
$active = User::all()->filter(fn ($u) => $u->active && $u->created_at->gt(now()->subMonth()))->count();
// After
$active = User::where('active', true)->where('created_at', '>', now()->subMonth())->count();
```

## Magic strings → backed enum with behavior

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Shipped => __('Shipped'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::Paid], true);
    }
}
// Model: protected function casts(): array { return ['status' => OrderStatus::class]; }
```

## switch → match

```php
$fee = match (true) {
    $amount < 100 => 5,
    $amount < 1000 => 10,
    default => $amount * 0.01,
};
```

`match` is strict (`===`), returns a value, and throws `UnhandledMatchError` for unhandled cases — fewer silent bugs than `switch`.

## Primitive obsession → readonly value object

```php
final readonly class Money
{
    public function __construct(public int $cents, public string $currency = 'MAD')
    {
        if ($cents < 0) throw new InvalidArgumentException('Negative amount');
    }
    public function add(Money $o): self
    {
        if ($o->currency !== $this->currency) throw new LogicException('Currency mismatch');
        return new self($this->cents + $o->cents, $this->currency);
    }
    public function format(): string { return number_format($this->cents / 100, 2, ',', ' ').' '.$this->currency; }
}
```

Use integer cents (or `brick/money`) — never floats for money.

## Long parameter list / boolean flags → DTO + named arguments

```php
// Before
createUser($name, $email, $phone, true, false, null, 'fr');
// After
final readonly class NewUserData {
    public function __construct(
        public string $name, public string $email, public ?string $phone = null,
        public bool $sendWelcome = true, public string $locale = 'fr',
    ) {}
}
$action->handle(new NewUserData(name: $name, email: $email, locale: 'fr'));
```

## Fat controller → Action class

```php
// app/Actions/PlaceOrder.php
final class PlaceOrder
{
    public function __construct(private PaymentGateway $payments) {}

    public function handle(User $user, Cart $cart): Order
    {
        return DB::transaction(function () use ($user, $cart) {
            $order = $user->orders()->create(['total' => $cart->total()]);
            $order->items()->createMany($cart->lines());
            $this->payments->charge($order);
            OrderPlaced::dispatch($order);
            return $order;
        });
    }
}
// Controller: return new OrderResource($placeOrder->handle($request->user(), $cart));
```

## Large data → generators / LazyCollection

```php
function readCsv(string $path): Generator {
    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
    foreach ($file as $row) { yield $row; }
}
LazyCollection::make(fn () => readCsv($path))->skip(1)->chunk(500)->each(fn ($chunk) => Product::upsert(...));
```

## Duplicated queries/logic → query scopes / shared methods

```php
// Model
public function scopePublished(Builder $q): void { $q->where('status', 'published')->where('published_at', '<=', now()); }
// Usage
Post::published()->latest('published_at')->paginate();
```

## WordPress legacy patterns

| Legacy | Modern |
|---|---|
| Functions in global namespace with no prefix | Namespaced classes / prefixed functions |
| `query_posts()` | `WP_Query` / `pre_get_posts` |
| `extract( $args )` | explicit `$args['key'] ?? default` / `wp_parse_args` |
| `mysql_*` / string SQL | `$wpdb->prepare()` |
| Inline `<script>` in templates | `wp_enqueue_script` + `wp_add_inline_script` |
| `create_function` | closures / first-class callables `$this->method(...)` |

## Legacy PHP → PHP 8.x quick wins

- Constructor property promotion, typed properties, union/nullable types, `?->`, `??=`, `str_contains/starts_with/ends_with`, first-class callable syntax `strlen(...)`, `readonly`, enums, `never` return type, `#[\Override]` (8.3), typed class constants (8.3).
- Rector does most of this automatically (see tooling.md).
