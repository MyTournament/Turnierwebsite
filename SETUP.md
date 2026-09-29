# Setup

## Local development
Any local PHP stack works (e.g. [EasyPHP Devserver](https://www.easyphp.org/documentation/devserver/getting-started.php),
XAMPP or the built-in PHP server). Open the project via `index.php`.

```bash
php -S localhost:8000
```

Credentials are loaded from `local_secrets/*.local.php` (see the `*.example.php` templates).

## Database
The production database runs on a MySQL/MariaDB server. For local development, create an empty
database, import a dump and enter the connection data in `local_secrets/db_connection.local.php`.

## Deployment
Every push to `main` is deployed automatically to the production server by the GitHub Actions
workflow in `.github/workflows/main.yml` (FTP sync).

For manual uploads (e.g. via WinSCP), grant the deployment user write access to the web root:

```bash
sudo apt-get install acl
sudo setfacl -R -m u:<deploy-user>:rwx /var/www
```

## Web server
The site runs on Apache. Virtual hosts are configured under `/etc/apache2/sites-available/`;
the included `.htaccess` maps error responses to a custom error page.
