---
tags: [skill-creator]
max_turns: 20
timeout_seconds: 600
allowed_tools: [Read, Glob, Grep, Skill, Write, Edit, Bash]
---

Refactor this please, it's from an old project (PHP 8.3 now):

```php
function processOrders($orders, $customers, $type, $sendEmail) {
    $result = array();
    for ($i = 0; $i < count($orders); $i++) {
        if ($orders[$i]['status'] != 'cancelled') {
            if ($type == 1) {
                foreach ($customers as $c) {
                    if ($c['id'] == $orders[$i]['customer_id']) {
                        $orders[$i]['customer_name'] = $c['name'];
                        if ($sendEmail == true) {
                            mail($c['email'], 'Order', 'Your order ' . $orders[$i]['id'] . ' is ' . $orders[$i]['status']);
                        }
                    }
                }
                $result = array_merge($result, array($orders[$i]));
            } else if ($type == 2) {
                $orders[$i]['total'] = $orders[$i]['total'] * 1.2;
                $result = array_merge($result, array($orders[$i]));
            }
        }
    }
    return $result;
}
```
