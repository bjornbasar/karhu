# Installation

## Requirements

| | |
|---|---|
| **PHP** | 8.3 or 8.4 |
| **Composer** | 2.x |
| **Extensions** | `json`, `mbstring` (both bundled with virtually every build) |
| **For `PasswordHasher`** | Argon2 support — standard in the official Docker images and distro packages |
| **Runtime dependencies** | **none** ([ADR 0003](adr/0003-zero-runtime-deps.md)) |

karhu's `require` block contains exactly one entry, `php: >=8.3`. Installing it pulls nothing
else into your vendor directory.

Check your version:

```bash
php -v            # must report 8.3 or newer
php -m | grep -i -E 'json|mbstring'
```

---

## Add karhu to a project

```bash
composer require bjornbasar/karhu
```

That is the whole install. The current release is **v0.1.4**.

!!! note "The `vendor/bin/karhu` shim needs v0.1.5"
    The `bin` key that tells Composer to link the CLI into `vendor/bin/` was added after v0.1.4.
    On v0.1.4, run it by its real path instead:

    ```bash
    php vendor/bjornbasar/karhu/bin/karhu list
    ```

!!! warning "`composer create-project bjornbasar/karhu-skeleton` does not work yet"
    The skeleton exists [on GitHub](https://github.com/bjornbasar/karhu-skeleton) but has **not
    been submitted to Packagist**, so `create-project` fails with a package-not-found error.
    Clone it instead — see [Start from the skeleton](#start-from-the-skeleton) below.

    Published today: `karhu`, `karhu-db`, `karhu-queue`, `karhu-view`.

---

## Start from scratch

Three files are enough for a working application.

**1. Install and set up autoloading**

```bash
mkdir myapp && cd myapp
composer require bjornbasar/karhu
```

Add a PSR-4 namespace for your own code to `composer.json`:

```json
{
    "require": {
        "bjornbasar/karhu": "^0.1.4"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

```bash
composer dump-autoload
```

**2. A controller — `app/Controllers/HomeController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class HomeController
{
    #[Route('/', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return (new Response())->withBody('Hello from karhu');
    }

    #[Route('/hello/{name}', methods: ['GET'])]
    public function hello(Request $request): Response
    {
        return (new Response())->json(['hello' => $request->routeParams()['name']]);
    }
}
```

**3. The controller registry — `config/controllers.php`**

```php
<?php

return [
    App\Controllers\HomeController::class,
];
```

Controllers are listed explicitly rather than discovered by scanning the filesystem. It is one
line per controller, and it is also the exact list `vendor/bin/karhu route:cache` compiles.

**4. The front controller — `public/index.php`**

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

**5. Run it**

```bash
php -S localhost:8080 -t public
```

```bash
curl localhost:8080
# Hello from karhu

curl localhost:8080/hello/world
# {"hello":"world"}
```

Your tree:

```
myapp/
├── app/Controllers/HomeController.php
├── config/controllers.php
├── public/index.php
├── composer.json
└── vendor/
```

---

## Start from the skeleton

The skeleton is the same layout plus a CLI entry point, an example command, and Apache/nginx
configs. Until it is on Packagist, clone it:

```bash
git clone https://github.com/bjornbasar/karhu-skeleton.git myapp
cd myapp
rm -rf .git
composer install
composer serve      # http://localhost:8080
```

It ships:

```
myapp/
├── app/Controllers/HomeController.php
├── app/Commands/HelloCommand.php
├── config/controllers.php
├── config/commands.php
├── public/index.php
├── public/.htaccess
└── docs/deployment/{apache.conf,nginx.conf}
```

!!! note "The skeleton tracks `dev-main`"
    Its `composer.json` requires `bjornbasar/karhu: dev-main` with `minimum-stability: dev`, so a
    fresh install gets the tip of `main` rather than the v0.1.4 release. Pin it for anything real:

    ```bash
    composer require bjornbasar/karhu:^0.1.4
    ```

---

## Companion packages

All optional, all installed the same way. None is required by karhu itself.

| Package | Install | What it adds |
|---|---|---|
| [karhu-db](packages/db.md) | `composer require bjornbasar/karhu-db` | PDO wrapper, active-record base, `PdoUserRepository` |
| [karhu-queue](packages/queue.md) | `composer require bjornbasar/karhu-queue` | Queue/worker abstraction with a database driver |
| [karhu-view](packages/view.md) | `composer require bjornbasar/karhu-view` | Twig and Plates adapters |

---

## Web server configuration

Every request must reach `public/index.php`, and **only** `public/` may be web-accessible.

=== "nginx"

    ```nginx
    server {
        listen 80;
        root /var/www/myapp/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    ```

=== "Apache"

    ```apache
    <VirtualHost *:80>
        DocumentRoot /var/www/myapp/public

        <Directory /var/www/myapp/public>
            AllowOverride All
            Require all granted
        </Directory>
    </VirtualHost>
    ```

    With `public/.htaccess`:

    ```apache
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
    ```

=== "PHP built-in (dev only)"

    ```bash
    php -S localhost:8080 -t public
    ```

    Single-threaded — a request that makes a request to itself will deadlock. Never use it in
    production.

!!! warning "Point the document root at `public/`, not the project root"
    Serving the project root exposes `.env`, `vendor/`, `config/` and your source to anyone who
    guesses a path.

### Deploying under a sub-directory

```php
$app->setBasePath('/myapp');
```

The prefix is stripped before matching and re-added by `Router::urlFor()`.

---

## Verify the install

```bash
php -r 'require "vendor/autoload.php"; echo Karhu\App::class, PHP_EOL;'
# Karhu\App
```

Then head to [Getting Started](getting-started.md) to add middleware, validation and auth — or
straight to the [Tutorial](tutorial/index.md) to build a real application.
