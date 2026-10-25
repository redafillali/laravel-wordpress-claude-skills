# Claude Skills for Laravel & WordPress Developers

[![Validate skills](https://github.com/redafillali/laravel-wordpress-claude-skills/actions/workflows/validate.yml/badge.svg)](https://github.com/redafillali/laravel-wordpress-claude-skills/actions/workflows/validate.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![Skills](https://img.shields.io/badge/skills-11-8A2BE2)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11%2B-FF2D20?logo=laravel&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-6.x-21759B?logo=wordpress&logoColor=white)

A curated set of [Agent Skills](https://docs.claude.com/en/docs/agents-and-tools/agent-skills/overview) that make Claude a better pair-programmer for PHP teams building with **Laravel** and **WordPress** — from day-to-day coding, security audits and performance tuning to migrating a WordPress site to Laravel, deploying it, and keeping its SEO intact.

> 🇫🇷 Une version française de ce README est disponible plus bas : [Version française](#-version-française).

## Table of contents

- [Skills](#skills)
- [Installation](#installation)
- [Usage examples](#usage-examples)
- [How skills work](#how-skills-work)
- [Repository layout](#repository-layout)
- [Testing & validation](#testing--validation)
- [Contributing](#contributing)
- [License](#license)
- [Version française](#-version-française)

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

### Claude Code — plugin marketplace (recommended)

Inside Claude Code:

```bash
/plugin marketplace add redafillali/laravel-wordpress-claude-skills
/plugin install laravel-wordpress-skills@laravel-wordpress-claude-skills
```

This installs all 11 skills. Update later with `/plugin marketplace update laravel-wordpress-claude-skills`.

### Claude Code — manual

```bash
git clone https://github.com/redafillali/laravel-wordpress-claude-skills.git
cd laravel-wordpress-claude-skills

# all projects (personal skills)
cp -r skills/* ~/.claude/skills/

# or a single project
mkdir -p /path/to/project/.claude/skills
cp -r skills/laravel-development /path/to/project/.claude/skills/
```

### Claude.ai / Claude desktop

Zip a skill folder (the folder itself, containing `SKILL.md`) and upload it in **Settings → Capabilities → Skills**:

```bash
cd skills && zip -r laravel-development.zip laravel-development
```

Or download the repository as a ZIP from [GitHub](https://github.com/redafillali/laravel-wordpress-claude-skills/archive/refs/heads/main.zip) and zip the skill folders you need.

### Claude API

Upload skills through the Skills API — see the [Agent Skills documentation](https://docs.claude.com/en/docs/agents-and-tools/agent-skills/overview).

## Usage examples

Skills load automatically when your request matches their description — no special syntax needed:

| You ask | Skill used |
|---|---|
| "My Laravel dashboard takes 6 s to load, here's the controller…" | `laravel-performance-tuning` |
| "This WooCommerce query takes 3 s, which index should I add?" | `mysql-optimization` |
| "Can you security-review this controller before we go live?" | `php-security-audit` |
| "My client's WordPress redirects mobile visitors to a casino site" | `wordpress-security-hardening` |
| "PageSpeed mobile is 34, LCP is 5.8 s on our Elementor homepage" | `core-web-vitals-optimization` |
| "Refactor this legacy PHP function" | `php-code-optimization` |
| "Set up a GitHub Actions deploy over FTP to my VPS" | `php-deploy-github-actions` |
| "Plan the migration of our WordPress site to Laravel without losing SEO" | `wp-to-laravel-migration` + `technical-seo-php` |

## How skills work

Each skill is a folder with a `SKILL.md`: YAML frontmatter (`name`, `description`) plus instructions. Claude always sees the short description and loads the full instructions only when relevant; deeper material in `references/` is read on demand, and `assets/` holds templates (workflows, boilerplate) to copy into your projects. This keeps context small while giving Claude expert, opinionated guidance when it needs it.

## Repository layout

```
.
├── .claude-plugin/marketplace.json   # Claude Code plugin marketplace manifest
├── .github/workflows/validate.yml    # CI: validates every SKILL.md on push/PR
├── scripts/validate_skills.py        # Local validator (same as CI)
└── skills/
    └── <skill-name>/
        ├── SKILL.md                  # Frontmatter (name, description) + instructions
        ├── references/               # Loaded on demand for deeper topics
        ├── assets/                   # Templates copied into projects
        └── evals/                    # Test cases (runnable with `claude plugin eval`)
```

## Testing & validation

Validate structure and frontmatter (also runs in CI):

```bash
pip install pyyaml
python scripts/validate_skills.py
```

Run a skill's test cases with and without the skill (Claude Code, billed model runs):

```bash
cd skills/php-security-audit
claude plugin eval . --allow-tools Write,Edit,Bash
```

## Contributing

Contributions are welcome — new skills, fixes, better examples or harder test cases. Read [CONTRIBUTING.md](CONTRIBUTING.md), then open an [issue](https://github.com/redafillali/laravel-wordpress-claude-skills/issues) or a [pull request](https://github.com/redafillali/laravel-wordpress-claude-skills/pulls).

## License

[MIT](LICENSE) © Reda El Fillali

---

## 🇫🇷 Version française

Un ensemble de **skills Claude** pour les développeurs PHP qui travaillent avec **Laravel** et **WordPress** : conventions de code, sécurité, performance, optimisation MySQL, refactoring, Core Web Vitals, migration WordPress → Laravel, déploiement via GitHub Actions et SEO technique.

### Les skills

- **laravel-development** — conventions, Eloquent (N+1, index), validation, sécurité, tests Pest/PHPUnit.
- **wordpress-development** — thèmes et plugins sécurisés : hooks, nonces, sanitize/escape, REST API, WP-CLI.
- **wp-to-laravel-migration** — méthode complète pour migrer un site WordPress vers Laravel sans perdre contenus, URLs ni positions SEO.
- **php-deploy-github-actions** — déploiement FTP/SFTP ou SSH + rsync vers mutualisé ou VPS, workflows prêts à l'emploi.
- **technical-seo-php** — balises meta, canonicals, sitemaps, schema.org, redirections 301.
- **laravel-performance-tuning** — diagnostic de lenteurs Laravel (mesure d'abord), cache, queues, OPcache, PHP-FPM, Octane.
- **mysql-optimization** — requêtes lentes, EXPLAIN, index, optimisation de la base WordPress, réglages InnoDB.
- **php-code-optimization** — refactoring sans régression, modernisation PHP 8.x, Rector/PHPStan/Pint.
- **php-security-audit** — audit OWASP Top 10 avec rapport classé par sévérité.
- **wordpress-security-hardening** — durcissement WordPress et procédure de nettoyage d'un site piraté.
- **core-web-vitals-optimization** — LCP, INP, CLS pour Laravel et WordPress.

### Installation rapide (Claude Code)

```bash
/plugin marketplace add redafillali/laravel-wordpress-claude-skills
/plugin install laravel-wordpress-skills@laravel-wordpress-claude-skills
```

Pour Claude.ai ou l'application de bureau : zippez le dossier d'un skill et importez-le dans **Paramètres → Capacités → Skills**. Les skills se déclenchent automatiquement selon votre demande.

Dépôt : https://github.com/redafillali/laravel-wordpress-claude-skills — les contributions sont les bienvenues ([issues](https://github.com/redafillali/laravel-wordpress-claude-skills/issues)).
