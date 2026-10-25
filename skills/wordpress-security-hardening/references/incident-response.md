# Hacked WordPress — incident response playbook

Work on a copy when possible. Record every command and finding (timeline).

## 1. Contain & preserve

```bash
# Forensic backup BEFORE cleaning
tar -czf ~/forensic-files-$(date +%F-%H%M).tgz -C /var/www/site public_html
wp db export ~/forensic-db-$(date +%F-%H%M).sql
# Copy access logs for the last 30 days
```

Put the site in maintenance (or restrict by IP at the server) if visitors are being redirected or served malware. Change the hosting panel / SSH / FTP passwords immediately if credential theft is suspected.

## 2. Investigate files

```bash
cd /var/www/site/public_html

# Core and plugin integrity (lists modified/added files)
wp core verify-checksums
wp plugin verify-checksums --all

# Recently modified PHP files (adjust days)
find . -name "*.php" -mtime -15 -printf '%TY-%Tm-%Td %TH:%TM  %p\n' | sort -r | head -50

# PHP files where they should never be
find wp-content/uploads -type f \( -name "*.php" -o -name "*.phtml" -o -name "*.phar" \)

# Common obfuscation patterns (expect some false positives in legit plugins)
grep -rEl --include=*.php "eval\s*\(\s*(base64_decode|gzinflate|str_rot13|gzuncompress)|assert\s*\(\s*\\\$_(POST|GET|REQUEST)|preg_replace\s*\(.*/e['\"]|\\\$_(POST|GET|REQUEST|COOKIE)\s*\[[^]]+\]\s*\(" .

# Long single lines (packed payloads)
find . -name "*.php" -size +1k -exec awk 'length > 5000 {print FILENAME; exit}' {} \;

# Tampered server config
find . -name ".htaccess" -o -name ".user.ini" -o -name "php.ini" | xargs ls -la
ls -la wp-content/mu-plugins/ 2>/dev/null
```

Also check: unknown files in the web root (`wp-*.php` look-alikes such as `wp-confg.php`), `wp-content/` top level, theme `functions.php` / `header.php` / `footer.php` tails, cron jobs of the system user (`crontab -l`).

## 3. Investigate the database

```bash
wp user list --role=administrator --fields=ID,user_login,user_email,user_registered
wp db query "SELECT ID, user_login, user_registered FROM $(wp db prefix)users ORDER BY user_registered DESC LIMIT 10"

# Injected scripts / iframes in content
wp db query "SELECT ID, post_title FROM $(wp db prefix)posts WHERE post_content REGEXP '<script|<iframe|eval\\(|atob\\(|document\\.write' LIMIT 50"

# Suspicious options (siteurl/home changed, injected code)
wp option get siteurl; wp option get home
wp db query "SELECT option_name, LENGTH(option_value) FROM $(wp db prefix)options WHERE option_value REGEXP '<script|base64_decode|eval\\(' "

# Rogue scheduled events
wp cron event list --fields=hook,next_run_relative,recurrence
```

SEO spam: check Search Console → Pages / Performance for unknown URLs (often Japanese/pharma keywords), and `site:yourdomain.com` searches.

## 4. Logs → entry point

```bash
# POST requests to unusual PHP files around first-infection date
grep "POST" access.log | grep -vE "wp-cron.php|admin-ajax.php|wp-login.php" | awk '{print $7}' | sort | uniq -c | sort -rn | head
# Requests to the malicious file you found
grep "suspicious-file.php" access.log*
```

Cross-check installed plugin versions (`wp plugin list --fields=name,version,update`) against vulnerability databases (WPScan, Patchstack, Wordfence Intelligence). Nulled plugins are a frequent source.

## 5. Clean

```bash
# Reinstall core (keeps wp-config & wp-content)
wp core download --force --skip-content --version=$(wp core version)
# Reinstall every plugin from wordpress.org at the same version
wp plugin install $(wp plugin list --field=name --status=active) --force
# Premium plugins/themes: reinstall from the vendor's official zip
```

- Delete PHP in uploads, unknown files, malicious mu-plugins, rogue users (`wp user delete <id> --reassign=<good admin id>`), rogue cron events (`wp cron event delete <hook>`), malicious options/content (after review).
- Restore `.htaccess` to a clean default.
- If a clean backup from before the infection exists and content changes since are small, restoring it (then updating everything) can be faster — but still find and close the entry point.

## 6. Rotate secrets

- `wp config shuffle-salts` (logs everyone out)
- Reset all administrator/editor passwords (`wp user update <id> --user_pass=...` or force reset)
- DB password (update wp-config), FTP/SFTP/SSH keys, hosting panel, email accounts, API keys stored in options/wp-config, payment gateway keys if exposed.

## 7. Recover & follow-up

- Apply hardening (see hardening-snippets.md), enable 2FA, WAF, file integrity monitoring.
- Search Console → Security issues → **Request review** once clean; remove spam URLs (return 404/410; use Removals tool for urgent cases).
- Monitor daily for 2 weeks: checksums, new admin users, modified files, Search Console.
- If personal or payment data may have been accessed, inform the owner about potential notification obligations (data protection authority / customers) and consider a professional forensic review.
