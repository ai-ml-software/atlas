# Deploying to a shared host

Written for a deployment into a subdirectory, for example
`https://khidmat.alwaysdata.net/atlas/`.

---

## Why you are seeing HTTP 500 with no message

`index.php` defaults `ENVIRONMENT` to `production`, and production sets
`display_errors = 0`. Any fatal error therefore reaches the browser as a bare
500 with nothing to act on. The error is real and the server knows what it is;
it is just not being shown.

Two ways to see it. Use the first.

### 1. Run the deployment check

Upload `deploy-check.php` beside `index.php`, set a key in it, then open:

```
https://khidmat.alwaysdata.net/atlas/deploy-check.php?key=YOUR_KEY
```

It tests PHP version and extensions, mod_rewrite, the `.htaccess`, the database
connection, directory permissions and the presence of the application files,
and prints the tail of the CodeIgniter log. Every failure comes with the fix.

**Delete the file when you are done.** It describes your server to anyone who
opens it.

### 2. Turn errors on for one request

In `index.php`, change:

```php
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production');
```

to `'development'`, reload the failing page, read the error, then change it
back. Never leave a live site in development mode: it prints file paths,
queries and credentials to visitors.

---

## The usual causes, most common first

### 1. Database credentials still point at your laptop

This is the most likely cause. `application/config/database.php` currently
holds the local development values:

```php
'hostname' => '127.0.0.1',
'username' => 'root',
'password' => 'root',
'database' => 'atlas_hospitality',
```

On alwaysdata none of those exist. Replace them with the values from your
host's control panel. On alwaysdata the host is usually
`mysql-<account>.alwaysdata.net` and the database is `<account>_something`.

A failed connection in production mode produces exactly the blank 500 you saw.

### 2. The database is empty

Export from the working local database and import on the server:

```bash
# locally
mysqldump -uroot -proot --default-character-set=utf8mb4 \
  --single-transaction --routines atlas_hospitality > atlas.sql

# on the host, through phpMyAdmin or the shell
mysql -h <host> -u <user> -p <database> < atlas.sql
```

Keep `utf8mb4`. Anything else mangles the Arabic content and the Arabic slugs.

### 3. PHP version

The application needs PHP 7.4 or newer and is developed on 8.3. On alwaysdata
the PHP version is set per site in the admin panel.

### 4. Missing extensions

Required: `mysqli`, `mbstring`, `json`. Needed by the photo pipeline: `gd`
with WebP, and `curl`. The deployment check reports each one.

### 5. Directory permissions

These must be writable by the web user:

```
application/logs
application/cache
uploads
uploads/academy
uploads/thumbnails
```

`chmod 755` is usually right, `775` if the web user is in your group. Without a
writable `application/logs` you cannot read the error that caused the 500.

### 6. A directive your host rejects

Shared hosts commonly return 500 rather than ignoring an unsupported directive.
The shipped `.htaccess` avoids the usual offenders: no `php_value`, no
`php_flag`, no `AddHandler`, no `Options +FollowSymLinks`, and Apache 2.4
syntax guarded by `<IfModule>`.

To test whether `.htaccess` is the cause at all, rename it to `htaccess.txt`
and reload. If the 500 becomes a working home page with broken links, the file
is the problem. If the 500 stays, it is not.

---

## The .htaccess

The shipped file works at a domain root and in any subdirectory without
editing, because the rewrite rule is relative to the directory holding it.
`RewriteBase` is left commented out on purpose.

Uncomment and set it **only** if you get a redirect loop or every URL resolves
to the document root:

```apache
RewriteBase /atlas/
```

It also blocks `application/` and `system/`, refuses to execute anything under
`uploads/`, hides dotfiles and `.sql`, `.log` and `.md` files, sets basic
security headers, and enables compression and far-future caching for static
assets.

Verify after deploying:

```
https://khidmat.alwaysdata.net/atlas/                      the site
https://khidmat.alwaysdata.net/atlas/en                    pretty URLs work
https://khidmat.alwaysdata.net/atlas/application/config/config.php   must be 403
https://khidmat.alwaysdata.net/atlas/README.md             must be 403
```

If `/atlas/` loads but `/atlas/en` is a 404, mod_rewrite is not active for the
directory. On alwaysdata that usually means the site is configured as a static
or PHP-CGI site rather than an Apache site with `.htaccess` enabled.

---

## HTTPS behind a proxy

alwaysdata terminates TLS at a proxy and forwards plain HTTP, so
`$_SERVER['HTTPS']` is not set even though visitors are on `https://`.
Unhandled, CodeIgniter builds every asset, canonical and redirect URL with
`http://`, and the browser blocks them as mixed content.

`application/config/config.php` now also reads `X-Forwarded-Proto`,
`X-Forwarded-SSL` and port 443, so the base URL is correct behind a proxy. No
action needed; this is noted because it is not obvious when links come out
wrong.

---

## After the site loads

The academy content lives in the `ha_*` tables and is published into the
Academy LMS tables by a bridge command. If you imported a full dump, both are
already present and there is nothing to run.

If you imported only a partial dump, or you change the catalogue, run:

```bash
php index.php ha_cli migrate
php index.php ha_cli seed
php index.php ha_bridge sync
```

Most shared hosts give you SSH. On alwaysdata you have it. If you do not, run
these locally against the remote database by temporarily pointing
`application/config/database.php` at the host.

---

## Before this is public

- Change every seeded password. The demo accounts in `README.md` all use
  `Academy#2026` and the super admin uses `admin123`.
- Delete `deploy-check.php`.
- Delete or protect the `.lab/` directory. It holds screenshots and audit
  output, not application code.
- Confirm `ENVIRONMENT` is `production` in `index.php`.
- Set `$config['log_threshold']` to `1` in `application/config/config.php` so
  real errors are recorded, and check `application/logs` is writable.
- Point `$config['encryption_key']` in `application/config/config.php` at a
  long random value. It is currently empty.
