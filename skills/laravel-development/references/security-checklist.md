# Laravel security review checklist

Walk through each section; report findings as **Critical / High / Medium / Low** with file:line and the fix.

## Input & output
- [ ] Every controller action validates input (Form Request or `$request->validate()`); downstream code uses `validated()` / `safe()`.
- [ ] No `$request->all()` passed to `create()`, `update()`, `fill()`.
- [ ] Models declare `$fillable` (or `$guarded` with care); sensitive columns (`is_admin`, `role`, `balance`) are not fillable.
- [ ] Blade uses `{{ }}`; every `{!! !!}` is justified and the content sanitized (e.g. HTML Purifier).
- [ ] No user input in `whereRaw`, `orderByRaw`, `selectRaw`, `DB::statement` without bindings; dynamic column/sort names are whitelisted.
- [ ] JSON APIs return API Resources, not raw models; `$hidden` covers `password`, `remember_token`, tokens.

## Authentication & authorization
- [ ] Every route touching a resource authorizes (Policy, `can` middleware, `Gate::authorize`). Check for IDOR: can user A access `/invoices/{id}` of user B?
- [ ] Livewire public methods and properties are authorized and locked (`#[Locked]`) where needed.
- [ ] Login, register, password reset, OTP and public API endpoints are rate-limited.
- [ ] Passwords hashed with `Hash::make` (default bcrypt/argon); password rules via `Password::defaults()`.
- [ ] Sanctum tokens have abilities and expiration; tokens revoked on logout/password change.
- [ ] Session regenerated on login (built into Laravel auth scaffolding — check custom auth).

## Requests & transport
- [ ] CSRF middleware active; exclusions limited to signed webhooks with signature verification.
- [ ] `APP_DEBUG=false`, `APP_ENV=production` in production; no `dd()`/`dump()` left.
- [ ] HTTPS enforced (`URL::forceScheme('https')` behind proxies, `TrustProxies` configured), secure + httpOnly + SameSite cookies.
- [ ] CORS (`config/cors.php`) not `*` with credentials.
- [ ] Security headers (CSP, X-Frame-Options/frame-ancestors, X-Content-Type-Options, Referrer-Policy) via middleware or web server.

## Files
- [ ] Uploads validated (`file`, `mimes`, `max`), stored with generated names, on a private disk unless public by design.
- [ ] No path built from user input without `basename()`/whitelist (path traversal).
- [ ] Downloads of private files go through an authorized controller or temporary signed URLs.

## Secrets & dependencies
- [ ] `.env` not committed; `APP_KEY` set and secret; keys rotated if leaked.
- [ ] `composer audit` clean; `npm audit` reviewed.
- [ ] Mass-assignable or serializable classes don't unserialize user input (`unserialize()` on user data is a red flag).

## Business logic
- [ ] Money and stock updates in `DB::transaction()` with row locks (`lockForUpdate()`) where concurrency matters.
- [ ] Signed URLs (`URL::temporarySignedRoute`) for email links and unsubscribes; `signed` middleware applied.
- [ ] Queued jobs re-check authorization/state when they run (state may have changed).
- [ ] Logs don't contain passwords, tokens, or full card/ID numbers.
