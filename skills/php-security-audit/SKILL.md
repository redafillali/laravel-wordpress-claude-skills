---
name: php-security-audit
description: Structured security audit of PHP code and applications (Laravel, WordPress plugins/themes, legacy PHP) mapped to the OWASP Top 10 — injection, broken access control and IDOR, authentication and session flaws, XSS, CSRF, SSRF, file upload and path traversal, insecure deserialization, mass assignment, secrets exposure, misconfiguration and vulnerable dependencies — with tool-assisted scanning (composer audit, PHPStan/Larastan, Psalm taint analysis, Semgrep) and a severity-ranked report with fixes. Use this skill whenever the user asks for a security review, pentest-style code review, vulnerability check, OWASP audit, "is this code safe", pre-launch security checklist, or shares PHP code that handles user input, authentication, payments, uploads or admin features — even if they don't explicitly say "audit".
---

# PHP Security Audit

Goal: find the vulnerabilities that an attacker could realistically exploit, prove them with the code path (source → sink), and give a precise fix. A short list of real, well-explained findings beats a long list of generic advice.

## 1. Scope & context

Establish quickly: framework and version, entry points (routes, controllers, AJAX/REST handlers, CLI, webhooks, cron), authentication model and roles, sensitive assets (personal data, payments, admin functions, files), and deployment (shared hosting, VPS, containers). If the user only shared a snippet, audit it and list what you'd need to confirm context-dependent findings.

## 2. Method: trace sources to sinks

**Sources** (attacker-controlled): `$_GET/$_POST/$_REQUEST/$_COOKIE/$_FILES/$_SERVER` (incl. `HTTP_HOST`, `HTTP_X_FORWARDED_*`), `$request->input()/all()/query()/header()/file()`, route parameters, uploaded file names/contents, webhook payloads, imported CSV/XML, data from DB that users wrote (stored XSS), third-party API responses.

**Sinks** (dangerous when reached by tainted data):

| Vuln class | Sinks to grep |
|---|---|
| SQL injection | `DB::raw`, `whereRaw`, `orderByRaw`, `selectRaw`, `havingRaw`, `DB::statement/select/unprepared`, `$wpdb->query/get_*` without `prepare`, `mysqli_query`, PDO `query()` with concatenation |
| Command injection | `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backticks, `Process::run($string)` |
| Code injection | `eval`, `assert` (string), `create_function`, `preg_replace` /e, `include/require` with variables, `call_user_func($input)` |
| XSS | `{!! !!}`, `echo` without `esc_*`, `->with()` into raw JS, `innerHTML` built from API data, `Response::make($html)` |
| Deserialization | `unserialize` on user data, `phar://` in file functions |
| SSRF | `Http::get($url)`, `file_get_contents($url)`, `curl_*`, `wp_remote_get` with user-supplied URLs |
| Path traversal / LFI | `Storage::get`, `file_get_contents`, `fopen`, `unlink`, `response()->download()`, `include` with user paths |
| Open redirect | `redirect($input)`, `redirect()->away()`, `wp_redirect($input)` |
| Mass assignment | `create($request->all())`, `fill($request->all())`, `$guarded = []` |

Then check **missing controls**, which grep can't find: authorization on each action (IDOR), CSRF on state changes, rate limiting on auth, validation on uploads, secure session/cookie settings.

Detailed checks per OWASP category with vulnerable/fixed examples: [references/owasp-php-checklist.md](references/owasp-php-checklist.md).

## 3. Tooling (run when you have shell access)

```bash
composer audit                                   # known-vulnerable dependencies
./vendor/bin/phpstan analyse --level=6           # (Larastan for Laravel) type & logic bugs
./vendor/bin/psalm --taint-analysis              # source→sink taint tracking
semgrep --config p/php --config p/owasp-top-ten --config p/laravel .   # pattern rules
grep -rnE "whereRaw|orderByRaw|DB::raw|\{!!|unserialize\(|shell_exec|exec\(|eval\(" app/ routes/ resources/
```

WordPress: `phpcs --standard=WordPress-Extra` flags unescaped output, missing nonces and unprepared SQL. Tools find candidates; confirm each by reading the code path — report only confirmed or clearly plausible issues, and label the rest "to verify".

## 4. Severity

Rate with impact × exploitability (CVSS-like, keep it simple):

- **Critical** — unauthenticated RCE, SQLi, auth bypass, full data exposure.
- **High** — authenticated privilege escalation, IDOR on sensitive data, stored XSS in admin, SSRF to internal services, arbitrary file upload.
- **Medium** — reflected XSS, CSRF on non-critical actions, user enumeration with impact, missing rate limit on login.
- **Low** — information disclosure (versions, stack traces), missing headers, weak defaults.

## 5. Report format

Use this structure:

```
# Security audit — <project / scope>
## Summary
<2–4 lines: overall posture, counts by severity, top risk>
## Findings
### [CRITICAL] <Title> — <file:line>
- Vulnerability: <class, OWASP category>
- Attack scenario: <who, how, what they gain — concrete request/payload description, no weaponized exploit>
- Vulnerable code: <snippet>
- Fix: <corrected code>
- Verification: <test or check proving the fix>
...
## Hardening recommendations (not vulnerabilities)
## Out of scope / to verify
```

## 6. Principles

- Fix at the right layer: parameterized queries, framework validation, policies, output escaping by context, allowlists over denylists.
- Prefer framework features over custom security code (Laravel `Gate`, `Validator`, `Hash`, `Crypt`, signed URLs; WordPress nonces, capabilities, `esc_*`, `$wpdb->prepare`).
- Each fix should come with a regression test when the project has tests (e.g. a feature test asserting 403 for another user's resource).
- Stay defensive: describe attack scenarios in enough detail to justify the fix, but don't produce weaponized exploit chains or payload kits.
