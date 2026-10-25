# One-time server setup

## Deploy user & key (SSH)

```bash
# On your machine: generate a dedicated key without passphrase
ssh-keygen -t ed25519 -C "github-deploy" -f deploy_key -N ""

# On the server (as root or sudo)
adduser --disabled-password deploy
usermod -aG www-data deploy
mkdir -p /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
cat deploy_key.pub >> /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys && chown -R deploy:deploy /home/deploy/.ssh

# Host key for GitHub secret SSH_KNOWN_HOSTS (verify the fingerprint!)
ssh-keyscan -H your-host.example.com
```

Put the **private** key `deploy_key` in the `SSH_PRIVATE_KEY` secret, then delete it locally if not needed.

On Virtualmin, the virtual server's own Unix user can be used as deploy user (enable SSH login for it); its home is usually `/home/<domain-user>/` with the web root in `public_html`.

## Laravel release layout

```bash
APP=/var/www/app
mkdir -p $APP/{releases,shared/storage}
mkdir -p $APP/shared/storage/{app/public,framework/{cache,sessions,views},logs}
cp .env.production $APP/shared/.env   # create it by hand, never commit it
chown -R deploy:www-data $APP
chmod -R ug+rwX $APP/shared/storage
```

Web server root → `/var/www/app/current/public`. Nginx example:

```nginx
root /var/www/app/current/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

Using `$realpath_root` (not `$document_root`) makes PHP-FPM resolve the symlink so OPcache sees the new release.

Optional sudo rule to reload FPM from CI (`visudo -f /etc/sudoers.d/deploy`):

```
deploy ALL=NOPASSWD: /bin/systemctl reload php8.3-fpm
```

## Queues and scheduler

```cron
* * * * * cd /var/www/app/current && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor program for workers pointing to `/var/www/app/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600`; deploy runs `queue:restart` so workers reload code.

## FTP (ProFTPD) passive mode

If GitHub runners time out on FTP listings, configure a passive port range and open it:

```
# /etc/proftpd/proftpd.conf
PassivePorts 49152 50000
# If behind NAT:
# MasqueradeAddress your.public.ip
```

```bash
ufw allow 21/tcp && ufw allow 49152:50000/tcp
systemctl restart proftpd
```

For FTPS, enable `mod_tls` with a valid certificate; otherwise use `protocol: ftp` (credentials then travel in clear — prefer SSH whenever possible).
