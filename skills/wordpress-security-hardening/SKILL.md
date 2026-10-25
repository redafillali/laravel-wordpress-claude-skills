---
name: wordpress-security-hardening
description: Harden WordPress sites and respond to compromises — layered hardening (wp-config, file permissions, server rules for Apache/Nginx, login protection, 2FA, XML-RPC, REST user enumeration, security headers, updates and plugin hygiene, backups, WAF), plus a structured incident-response playbook for hacked sites (malware, spam injections, redirects, unknown admin users, SEO spam) with WP-CLI and shell commands to investigate, clean, rotate secrets and prevent reinfection. Use this skill whenever the user asks to secure or audit a WordPress site, mentions a hacked/infected/blacklisted WordPress site, suspicious files or admin accounts, a redirect to spam sites, Google "This site may be hacked" warnings, brute-force attacks on wp-login.php, or wants a security checklist for a client site.
---

# WordPress Security Hardening & Incident Response

Two modes — identify which one the user is in:

- **Prevention** (site is healthy): apply layered hardening, prioritized by risk reduction per effort.
- **Incident** (site is or may be compromised): contain, investigate, clean, recover, then harden. Speed matters, but cleaning without finding the entry point leads to reinfection within days.

Most WordPress compromises come from **outdated or nulled plugins/themes**, **stolen/weak credentials**, and **writable files + PHP execution in uploads**. Prioritize those.

## Prevention — top 12 by impact

1. **Update** core, plugins, themes (auto-updates for minors and trusted plugins); remove unused and abandoned plugins/themes; never use nulled/pirated premium plugins.
2. **Admin accounts**: unique admins, strong passwords, **2FA** for all admin/editor roles, least privilege (no shared "admin" login), remove old users.
3. **Login protection**: rate limiting / lockout (server-level `limit_req` or fail2ban, or a security plugin), optional rename of login only as noise reduction, CAPTCHA on public forms.
4. **wp-config.php**: `DISALLOW_FILE_EDIT`, fresh salts, `WP_DEBUG_DISPLAY false`, `FORCE_SSL_ADMIN`, file one level above web root or protected; DB user with only needed privileges.
5. **No PHP in uploads** (server rule) and sensible **file permissions** (dirs 755, files 644, `wp-config.php` 640/600, owner ≠ web user where possible).
6. **Disable XML-RPC** if not used (Jetpack/mobile app need it), block `?author=N` enumeration and limit `/wp-json/wp/v2/users` for anonymous users.
7. **HTTPS + security headers** (HSTS, X-Content-Type-Options, X-Frame-Options/`frame-ancestors`, Referrer-Policy, Permissions-Policy; CSP when feasible).
8. **Backups**: automated, off-site, files + DB, tested restore, retention ≥ 30 days.
9. **WAF / edge**: Cloudflare (or similar) WAF + bot protection, or a plugin WAF (Wordfence) if no edge.
10. **Monitoring**: file integrity (`wp core verify-checksums`, `wp plugin verify-checksums --all`), uptime, Google Search Console security alerts, admin login notifications.
11. **Hosting isolation**: one system user per site (no cross-contamination), PHP version supported, `open_basedir`/disable dangerous functions where compatible.
12. **Code**: custom themes/plugins follow nonce/capability/sanitize/escape rules (see the `wordpress-development` skill).

Exact snippets for wp-config, Apache, Nginx and functions: [references/hardening-snippets.md](references/hardening-snippets.md).

## Incident response — playbook

Follow [references/incident-response.md](references/incident-response.md) step by step. Summary:

1. **Contain**: maintenance mode or block traffic, take a **forensic backup** (files + DB) before changing anything, note the time.
2. **Assess**: symptoms (redirects, spam pages, injected JS, unknown admins, new files), check Search Console/Safe Browsing, server access logs around the first sign.
3. **Investigate**: verify checksums of core/plugins, find recently modified PHP files, PHP in uploads, suspicious code patterns, rogue admin users, malicious options/cron/posts, `.htaccess`/`.user.ini` tampering, mu-plugins, DB injections.
4. **Find the entry point**: vulnerable plugin version (check WPScan/Wordfence/Patchstack vulnerability databases), compromised credentials (FTP/SSH/admin), other site on same account.
5. **Clean**: reinstall core/plugins/themes from official sources (don't "repair" infected files), remove backdoors, restore clean content, delete rogue users/cron/options.
6. **Rotate everything**: all WP passwords, salts (forces logout), DB password, FTP/SSH/hosting panel, API keys in wp-config/plugins.
7. **Harden** (list above), then **monitor** closely for 2–4 weeks; request review in Search Console if flagged; check for SEO spam URLs to remove (410/404 + removal requests).

When the user lacks shell/WP-CLI access, adapt with hosting file manager + phpMyAdmin, and recommend a professional cleanup service if the infection is extensive or the site handles payments/personal data (possible breach notification duties — mention checking legal obligations, e.g. Moroccan law 09-08 / CNDP, GDPR if EU users).

## How to answer

- Prevention: give a prioritized checklist with exact code/config, tailored to the server (Apache vs Nginx, shared hosting vs VPS, Cloudflare or not).
- Incident: guide step by step, ask for the outputs of investigation commands, interpret them, and never suggest deleting evidence before a backup.
- Don't provide offensive exploitation details; focus on detection, cleanup and prevention.
