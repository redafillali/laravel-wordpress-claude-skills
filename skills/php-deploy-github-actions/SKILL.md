---
name: php-deploy-github-actions
description: Set up and debug CI/CD for Laravel and WordPress projects with GitHub Actions — tests on push, then deployment to shared hosting or a VPS via FTP/FTPS/SFTP or SSH + rsync, including zero-downtime release folders, .env and secrets handling, migrations, cache warm-up and rollback. Use this skill whenever the user wants to deploy a PHP/Laravel/WordPress site from GitHub, write or fix a deploy.yml workflow, troubleshoot a failing FTP or SSH deploy step (timeouts, passive mode, permissions, "No such file"), or move from manual FTP uploads to automated deployments — even if they don't say "CI/CD".
---

# PHP Deploys with GitHub Actions

Goal: every push to the deployment branch runs checks, builds once, and ships a working release to the server — reproducibly, without committing secrets, and with a quick way back if something breaks.

## 1. Pick the transport (ask if unclear)

| Server access | Use | Notes |
|---|---|---|
| SSH available (VPS, most good hosts) | **SSH + rsync**, ideally release folders + `current` symlink | Fast, atomic, can run `php artisan migrate`. Preferred. |
| Only FTP/FTPS (shared hosting, Virtualmin/cPanel FTP accounts) | **FTP-Deploy-Action** (state-file diff sync) | Upload only changed files; can't run commands — use a protected deploy endpoint or cron for post-deploy tasks. |
| SFTP without shell | rsync over SFTP is not possible; use an SFTP sync action or `lftp mirror -R` | |

Ready-to-use workflows:
- [assets/laravel-ssh-deploy.yml](assets/laravel-ssh-deploy.yml) — test → build → rsync to release dir → migrate → switch symlink → keep last 5 releases.
- [assets/ftp-deploy.yml](assets/ftp-deploy.yml) — build then FTP/FTPS sync (Laravel or WordPress theme/plugin).
- [assets/wordpress-ssh-deploy.yml](assets/wordpress-ssh-deploy.yml) — deploy only `wp-content/themes/<theme>` and custom plugins via rsync, then WP-CLI cache flush.

Copy the relevant file to `.github/workflows/deploy.yml` and adapt the `env:` block.

## 2. Principles that prevent most incidents

- **Build in CI, not on the server**: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`. The server receives artifacts; this avoids missing Node/Composer on shared hosts and keeps builds identical.
- **Never deploy `.env`, `storage/`, uploads or `wp-config.php`.** They live on the server only (shared between releases via symlinks). Exclude them explicitly in rsync/FTP rules — a deploy that overwrites `storage/` or `wp-content/uploads` wipes data.
- **Secrets in GitHub** (`Settings → Secrets and variables → Actions`): `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_USER`, `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Use a dedicated deploy key/user with access limited to the site directory. Use **Environments** (`production`, `staging`) for approval gates and per-env secrets.
- **Pin `known_hosts`** (store the host key in a secret, `ssh-keyscan` once and verify) instead of disabling host key checking.
- **Concurrency**: `concurrency: { group: deploy-production, cancel-in-progress: false }` so two deploys never overlap.
- **Tests gate deploys**: deploy job `needs: tests`.
- **Migrations**: `php artisan migrate --force` runs before the symlink switch; write backward-compatible migrations (add columns first, remove later) so the old release still works during the switch.
- After switch: `php artisan optimize`, `php artisan queue:restart`, reload PHP-FPM if OPcache doesn't revalidate (`sudo systemctl reload php8.x-fpm` — or `opcache_reset` via a protected endpoint).

## 3. Zero-downtime layout (SSH)

```
/var/www/app/
├── releases/20261025093000/   # each deploy
├── shared/.env
├── shared/storage/
└── current -> releases/20261025093000
```

The web server document root points to `/var/www/app/current/public`. Rollback = point `current` to the previous release (and roll back migrations only if needed). One-time server setup steps are in [references/server-setup.md](references/server-setup.md).

## 4. Troubleshooting

Read [references/troubleshooting.md](references/troubleshooting.md) when a deploy fails. Quick hits:

- FTP timeouts / `ECONNRESET` / hangs on listing → passive mode ports blocked on the server firewall (ProFTPD `PassivePorts` + open the range), or switch `protocol: ftps` ↔ `ftp`; set `timeout`.
- FTP re-uploads everything every run → state file `.ftp-deploy-sync-state.json` missing or `server-dir` changed.
- `Permission denied (publickey)` → key not in `~/.ssh/authorized_keys` of the deploy user, wrong user, or key has a passphrase.
- 500 after deploy → missing `.env` symlink, `storage`/`bootstrap/cache` not writable, stale `config:cache` with wrong paths, PHP version mismatch between CI and server.
- WordPress white screen → plugin/theme PHP error; check `wp-content/debug.log`, PHP version.

## 5. How to answer

1. Confirm transport, branch, target path, PHP version, and whether assets must be built.
2. Give the complete workflow file + the list of secrets to create + one-time server commands.
3. Explain the exclusions (what must never be overwritten).
4. For debugging, ask for the failing step's log, then map the error to a cause and a fix.
