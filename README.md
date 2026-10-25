# Claude Skills for Laravel & WordPress Developers

A curated set of [Agent Skills](https://docs.claude.com/en/docs/agents-and-tools/agent-skills/overview) that make Claude a better pair-programmer for PHP shops building with **Laravel** and **WordPress** — from day-to-day coding, security audits and performance tuning to migrating a WordPress site to Laravel, deploying it, and keeping its SEO intact.

> 🇫🇷 Une version française de ce README est disponible plus bas : [Version française](#-version-française).

## Skills

| Skill | What it does | Triggers on |
|---|---|---|
| [`laravel-development`](skills/laravel-development) | Conventions, architecture, Eloquent performance, validation, security and testing (Pest/PHPUnit) for modern Laravel apps. | Writing or reviewing Laravel code, migrations, models, controllers, jobs, tests. |
| [`wordpress-development`](skills/wordpress-development) | Secure, standards-compliant themes and plugins: hooks, nonces, sanitize/escape, REST API, WP-CLI, performance. | Building or auditing WP themes/plugins, `functions.php`, Gutenberg blocks, WooCommerce tweaks. |
| [`wp-to-laravel-migration`](skills/wp-to-laravel-migration) | Step-by-step method to move a WordPress site to Laravel without losing content, URLs or rankings. | "Migrate my WordPress site to Laravel", rebuilding a WP site as a custom app. |
| [`php-deploy-github-actions`](skills/php-deploy-github-actions) | CI/CD for Laravel & WordPress to shared hosting or a VPS via GitHub Actions (FTP/SFTP or SSH + rsync), with ready-to-use workflows. | Setting up deploys, debugging failing FTP/SSH deploys, zero-downtime releases. |
| [`technical-seo-php`](skills/technical-seo-php) | Technical SEO checklist and implementation for Laravel and WordPress: meta, canonicals, sitemaps, schema.org, redirects, Core Web Vitals. | SEO audits, redesigns, meta tags, sitemaps, structured data, 301 plans. |
| [`laravel-performance-tuning`](skills/laravel-performance-tuning) | Measure-first performance work: N+1, aggregates, caching (flexible/locks), queues, OPcache, PHP-FPM, Redis, Octane. | Slow pages, endpoints, jobs or imports; timeouts; scaling a Laravel app. |
| [`mysql-optimization`](skills/mysql-optimization) | Slow query log, EXPLAIN, composite/covering indexes, query rewrites, WordPress `wp_postmeta`/`wp_options` bloat, InnoDB tuning, online ALTERs. | Slow SQL, high MySQL CPU, "which index?", deadlocks, huge tables. |
| [`php-code-optimization`](skills/php-code-optimization) | Behavior-preserving refactoring: code smells, O(n²) loops, PHP 8.x modernization, enums/value objects, Rector/PHPStan/Pint. | "Refactor/clean up/optimize this code", legacy PHP upgrades. |
| [`php-security-audit`](skills/php-security-audit) | OWASP Top 10 audit for Laravel/WordPress/legacy PHP: source→sink tracing, tooling (composer audit, Psalm taint, Semgrep), severity-ranked report. | Security reviews, "is this code safe?", pre-launch audits. |
| [`wordpress-security-hardening`](skills/wordpress-security-hardening) | Layered WP hardening (wp-config, server rules, 2FA, XML-RPC, headers) + hacked-site incident-response playbook with WP-CLI. | Securing a WP site, malware, spam redirects, "this site may be hacked". |
| [`core-web-vitals-optimization`](skills/core-web-vitals-optimization) | LCP/INP/CLS diagnosis from PSI/CrUX and fixes for Laravel (Vite/Blade) and WordPress (cache, Elementor, third-party scripts). | PageSpeed scores, slow mobile pages, failed Core Web Vitals. |

## Installation

### Claude Code (plugin marketplace)

```bash
/plugin marketplace add <your-github-username>/laravel-wordpress-claude-skills
/plugin install laravel-wordpress-skills@laravel-wordpress-claude-skills
```

### Claude Code (manual)

Copy any skill folder into your personal or project skills directory:

```bash
# personal (all projects)
cp -r skills/laravel-development ~/.claude/skills/
# project only
cp -r skills/laravel-development .claude/skills/
```

### Claude.ai / Claude desktop

Zip a skill folder (the folder itself, containing `SKILL.md`) and upload it in **Settings → Capabilities → Skills**.

```bash
cd skills && zip -r laravel-development.zip laravel-development
```

### Claude API

Upload skills through the Skills API — see the [Agent Skills documentation](https://docs.claude.com/en/docs/agents-and-tools/agent-skills/overview).

## Repository layout

```
.
├── .claude-plugin/marketplace.json   # Claude Code plugin marketplace manifest
├── .github/workflows/validate.yml    # CI: validates every SKILL.md
├── scripts/validate_skills.py        # Local validator (same as CI)
└── skills/
    └── <skill-name>/
        ├── SKILL.md                  # Frontmatter (name, description) + instructions
        ├── references/               # Loaded on demand for deeper topics
        ├── assets/                   # Templates copied into projects
        └── evals/                    # Test cases (runnable with `claude plugin eval`)
```

## Validate locally

```bash
pip install pyyaml
python scripts/validate_skills.py
```

## Contributing

Contributions are welcome — new skills, fixes, better examples. Read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## License

[MIT](LICENSE) © Reda El Fillali

---

## 🇫🇷 Version française

Un ensemble de **skills Claude** pour les développeurs PHP qui travaillent avec **Laravel** et **WordPress** : conventions de code, sécurité, tests, migration WordPress → Laravel, déploiement via GitHub Actions et SEO technique.

- **laravel-development** — conventions, Eloquent (N+1, index), validation, sécurité, tests Pest/PHPUnit.
- **wordpress-development** — thèmes et plugins sécurisés : hooks, nonces, sanitize/escape, REST API, WP-CLI.
- **wp-to-laravel-migration** — méthode complète pour migrer un site WordPress vers Laravel sans perdre contenus, URLs ni positions SEO.
- **php-deploy-github-actions** — déploiement FTP/SFTP ou SSH + rsync vers mutualisé ou VPS, workflows prêts à l'emploi.
- **technical-seo-php** — balises meta, canonicals, sitemaps, schema.org, redirections 301, Core Web Vitals.

- **laravel-performance-tuning** — diagnostic de lenteurs Laravel (mesure d'abord), cache, queues, OPcache, PHP-FPM, Octane.
- **mysql-optimization** — requêtes lentes, EXPLAIN, index, optimisation de la base WordPress, réglages InnoDB.
- **php-code-optimization** — refactoring sans régression, modernisation PHP 8.x, Rector/PHPStan/Pint.
- **php-security-audit** — audit OWASP Top 10 avec rapport classé par sévérité.
- **wordpress-security-hardening** — durcissement WordPress et procédure de nettoyage d'un site piraté.
- **core-web-vitals-optimization** — LCP, INP, CLS pour Laravel et WordPress.
Installation : voir la section [Installation](#installation) ci-dessus. Les contributions sont les bienvenues.
