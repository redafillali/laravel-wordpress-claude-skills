---
tags: [skill-creator]
max_turns: 20
timeout_seconds: 600
allowed_tools: [Read, Glob, Grep, Skill, Write, Edit, Bash]
---

My Laravel 11 admin dashboard takes ~6 seconds to load in production (MySQL 8, ~200k orders, 15k customers). Here's the controller, what should I change? Please give me the fixed code.

```php
class DashboardController extends Controller
{
    public function index()
    {
        $orders = Order::all();
        $paidTotal = 0;
        foreach ($orders as $order) {
            if ($order->status == 'paid') { $paidTotal += $order->total; }
        }
        $latest = Order::orderBy('created_at', 'desc')->take(20)->get();
        $topCustomers = Customer::all()->sortByDesc(function ($c) { return $c->orders->sum('total'); })->take(10);
        $stats = [];
        foreach (['pending','paid','shipped','cancelled'] as $s) {
            $stats[$s] = Order::where('status', $s)->get()->count();
        }
        return view('admin.dashboard', compact('paidTotal', 'latest', 'topCustomers', 'stats'));
    }
}
```
In the blade I show `$order->customer->name` for each latest order.
