# Deploy troubleshooting

| Symptom (log) | Likely cause | Fix |
|---|---|---|
| FTP: `Timeout (control socket)`, hangs after `PASV` | Passive ports closed / NAT | Configure `PassivePorts` + firewall; `MasqueradeAddress` behind NAT; raise `timeout` |
| FTP: `ECONNRESET` / TLS errors | FTPS misconfigured, or server only supports explicit/implicit variant | Try `protocol: ftp` or `ftps-legacy`; check cert; check fail2ban didn't ban GitHub IPs |
| FTP: `530 Login incorrect` | Wrong secret, special chars, account disabled, IP restrictions | Re-enter secret; test with `lftp` locally; check server login restrictions |
| FTP uploads everything every time | State file deleted or `server-dir` changed | Keep `.ftp-deploy-sync-state.json` on server; don't change `server-dir` |
| FTP: `550 No such file or directory` | `server-dir` relative to FTP root is wrong, or missing trailing `/` | Log in manually to see the FTP root; set `server-dir: public_html/` |
| SSH: `Permission denied (publickey)` | Key not authorized, wrong user, CRLF in secret, passphrase | Re-add pub key; regenerate key without passphrase; paste the secret with trailing newline |
| SSH: `Host key verification failed` | `known_hosts` missing/outdated | Re-run `ssh-keyscan -H host`, verify, update secret |
| rsync: `command not found` on remote | rsync missing on server | `apt install rsync` or fall back to `scp`/tar over ssh |
| 500 after deploy (Laravel) | `.env` not linked, `storage` not writable, cached config with old paths | Check `storage/logs/laravel.log`; re-run `php artisan optimize:clear && php artisan optimize`; fix perms |
| `Class not found` after deploy | Dev deps expected, or autoload not dumped | `composer install --no-dev -o` in CI; check namespace case (Linux is case-sensitive) |
| Old code still served | OPcache with `validate_timestamps=0` + symlink | Use `$realpath_root`; reload PHP-FPM after switch |
| Migration fails mid-deploy | Non-backward-compatible schema change | Release stays inactive (symlink not switched); fix migration, redeploy |
| WordPress white screen | Fatal error in theme/plugin, PHP version | `WP_DEBUG_LOG`, `wp-content/debug.log`; match PHP version in CI |
| Works locally, fails in CI tests | Missing PHP extension, env var, DB service | Add `extensions:` in setup-php; set env in workflow; add a MySQL service container |

Debugging tips:
- Re-run the job with **debug logging** (`ACTIONS_STEP_DEBUG` secret = `true`).
- Reproduce the transport locally with the same credentials (`lftp`, `ssh -v`).
- Add `set -x` in shell steps temporarily — never echo secrets.
