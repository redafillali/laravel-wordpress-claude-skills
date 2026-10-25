# OWASP Top 10 checklist for PHP (Laravel & WordPress)

## A01 Broken Access Control

- Every route/action that reads or changes a resource checks ownership/permission (Policy, `Gate::authorize`, `current_user_can`).
- IDOR test: change the ID in the URL/body to another user's — must return 403/404.
- Admin routes protected by middleware/role, not only hidden from menus.
- Livewire/Inertia/AJAX actions authorize server-side; hidden fields like `user_id`, `role`, `price` are never trusted.

```php
// ❌
public function show(Invoice $invoice) { return view('invoices.show', compact('invoice')); }
// ✅
public function show(Invoice $invoice) { $this->authorize('view', $invoice); return view(...); }
// or scope: $invoice = $request->user()->invoices()->findOrFail($id);
```

## A02 Cryptographic Failures

- Passwords with `Hash::make` / `password_hash` (bcrypt/argon2id); never md5/sha1, never reversible encryption.
- Sensitive data at rest encrypted where needed (`encrypted` cast, `Crypt`); keys in env, not in code.
- HTTPS enforced, HSTS; cookies `Secure`, `HttpOnly`, `SameSite=Lax/Strict`.
- Tokens from `Str::random()` / `random_bytes()`, never `rand()`, `uniqid()`, `md5(time())`.

## A03 Injection

```php
// ❌ SQLi
User::whereRaw("email = '$email'")->first();
DB::select("SELECT * FROM users ORDER BY $sort");
// ✅
User::where('email', $email)->first();
$sort = in_array($sort, ['name', 'created_at'], true) ? $sort : 'created_at';
User::orderBy($sort)->get();
```

```php
// ❌ Command injection
shell_exec("convert {$request->file} out.png");
// ✅
Process::run(['convert', $path, 'out.png']);   // array form, no shell
// or escapeshellarg() for each argument when a shell is unavoidable
```

Also: LDAP, XPath, header injection (CRLF in `header()`), email header injection (user input in headers of `mail()`).

## A04 Insecure Design

- Business rules enforced server-side: prices recomputed from DB, quantities validated, workflow states checked.
- Race conditions on balances/stock/coupons: transactions + `lockForUpdate()` + unique constraints.
- Password reset tokens single-use, expiring, bound to user; no user enumeration in reset flow responses.

## A05 Security Misconfiguration

- `APP_DEBUG=false`, `WP_DEBUG_DISPLAY=false` in production; no `phpinfo()`, Telescope/Debugbar exposed, `.env`/`.git` not web-accessible.
- Document root = `public/` (Laravel), not the project root.
- CORS not `*` with credentials; security headers present; directory listing off.
- Default credentials removed; error pages don't leak stack traces.

## A06 Vulnerable & Outdated Components

- `composer audit`, `npm audit`; PHP version supported; WordPress plugins checked against vulnerability databases; abandoned packages replaced.

## A07 Identification & Authentication Failures

- Rate limiting on login, 2FA, OTP, password reset (`throttle`, `RateLimiter`).
- Session regeneration on login (`$request->session()->regenerate()`), invalidation on logout/password change.
- Generic error messages ("Invalid credentials").
- API tokens scoped, expiring, revocable; JWT validated (alg, exp, signature) if used.

## A08 Software & Data Integrity Failures

```php
// ❌ PHP object injection
$prefs = unserialize($_COOKIE['prefs']);
// ✅
$prefs = json_decode($request->cookie('prefs', '{}'), true, flags: JSON_THROW_ON_ERROR);
```

- Webhooks verify signatures (Stripe, GitHub…) with constant-time comparison (`hash_equals`).
- Signed URLs for sensitive links (`URL::signedRoute` + `signed` middleware).
- CI/CD secrets protected; dependencies from trusted sources; no nulled plugins.

## A09 Logging & Monitoring Failures

- Auth failures, permission denials, admin actions logged with context (user id, IP) — without passwords/tokens/full card numbers.
- Alerts on spikes (failed logins, 500s); logs retained and not web-accessible.

## A10 Server-Side Request Forgery

```php
// ❌
Http::get($request->input('url'));
// ✅ allowlist host + scheme, block private ranges after DNS resolution
$url = $request->validate(['url' => ['required', 'url:https']])['url'];
$host = parse_url($url, PHP_URL_HOST);
abort_unless(in_array($host, config('services.fetch.allowed_hosts'), true), 422);
```

Also block redirects to internal IPs (`Http::withOptions(['allow_redirects' => false])`) and cloud metadata (169.254.169.254).

## XSS (A03) by context

| Context | Laravel | WordPress |
|---|---|---|
| HTML body | `{{ $v }}` | `esc_html()` |
| Attribute | `{{ $v }}` (quoted attribute) | `esc_attr()` |
| URL | validate scheme + `{{ }}` | `esc_url()` |
| JS data | `@json($v)` / `Js::from($v)` | `wp_json_encode()` |
| Rich HTML | HTML Purifier (`mews/purifier`) | `wp_kses_post()` |

Beware `javascript:` URLs in user-provided links, SVG uploads (can contain script), and Markdown renderers allowing raw HTML (`Str::markdown($text, ['html_input' => 'strip'])`).

## CSRF

- Laravel: `@csrf` in forms, `VerifyCsrfToken` active; exclusions only for signed webhooks.
- WordPress: nonces on every state-changing request + capability checks.
- GET requests must never change state.

## File uploads & path traversal

```php
$request->validate(['avatar' => ['required', 'image', 'mimes:jpg,png,webp', 'max:2048']]);
$path = $request->file('avatar')->store('avatars', 'private');   // generated name
```

- Never use the client filename for storage paths; never serve uploads from a PHP-executing directory.
- For downloads, map IDs to stored paths; never `Storage::download($request->path)`.

## Mass assignment

- `$fillable` explicit; `$request->validated()` only; privileged attributes (`is_admin`, `role`, `tenant_id`, `price`) set explicitly in code.
