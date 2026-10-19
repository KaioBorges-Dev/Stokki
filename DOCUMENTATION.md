# Stockki — Production Architecture & Deployment Documentation

This document provides a comprehensive, end-to-end guide for installing, configuring, developing, and hosting the **Stockki** Multi-Tenant Stock and Expiry Control System from scratch, including a dedicated production chapter for **cPanel** shared and dedicated hosting environments.

---

## Table of Contents
1. [System Architecture & Prerequisites](#1-system-architecture--prerequisites)
2. [Step-by-Step Local Installation (From Scratch)](#2-step-by-step-local-installation-from-scratch)
3. [Database Configuration & Migration Blueprint](#3-database-configuration--migration-blueprint)
4. [Authentication & Multi-Tenant Scoping](#4-authentication--multi-tenant-scoping)
5. [Automated Expiry Alerts & Scheduled Tasks](#5-automated-expiry-alerts--scheduled-tasks)
6. [Hosting on cPanel (Complete Production Guide)](#6-hosting-on-cpanel-complete-production-guide)
7. [Mobile App (Flutter 3.x) Setup](#7-mobile-app-flutter-3x-setup)
8. [Troubleshooting & Maintenance](#8-troubleshooting--maintenance)

---

## 1. System Architecture & Prerequisites

### 1.1 Requirements Matrix
| Component | Minimum Version | Recommended Version |
| :--- | :--- | :--- |
| **PHP** | 8.3.0 | 8.3+ or 8.4+ |
| **Composer** | 2.5.0 | 2.7+ |
| **Web Server** | Apache 2.4+ / Nginx 1.22+ | Apache with `mod_rewrite` enabled |
| **Database** | PostgreSQL 15+ (or MySQL 8.0+ / SQLite 3.35+) | PostgreSQL 16+ with UUID support |
| **Node.js** | 18.x LTS | 20.x or 22.x LTS |
| **Flutter SDK** | 3.19.0 | 3.22+ |

### 1.2 Mandatory PHP Extensions
Ensure the following extensions are active in `php.ini`:
- `pdo`
- `pdo_pgsql` (or `pdo_mysql` / `pdo_sqlite`)
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `curl`
- `gd` or `imagick` (for image processing and label cropping)
- `fileinfo`

---

## 2. Step-by-Step Local Installation (From Scratch)

### Step 2.1: Clone the Repository
```bash
git clone https://github.com/KaioBorges-Dev/Stokki.git
cd Stokki
```

### Step 2.2: Configure Environment Variables
Copy the example environment configuration:
```bash
cp .env.example .env
```
Open `.env` and verify database and application settings:
```ini
APP_NAME=Stockki
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stockki_db
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password
```

### Step 2.3: Install Backend Dependencies & Generate Key
```bash
composer install --optimize-autoloader
php artisan key:generate
```

### Step 2.4: Execute Database Migrations & Seeders
```bash
php artisan migrate --seed
```
*Note: For lightweight local development without PostgreSQL running, initialize the bundled SQLite engine using:*
```bash
php database/init_dev_db.php
```

### Step 2.5: Launch Local Server
```bash
php -S 127.0.0.1:8000 -t public public/index.php
# OR using standard artisan
php artisan serve --port=8000
```
Access the application at `http://127.0.0.1:8000`.

---

## 3. Database Configuration & Migration Blueprint

Stockki enforces strict database schema design:
- **UUIDv4 Primary Keys**: Non-sequential globally unique identifiers across all tenants.
- **`timestamptz` Precision**: Timezone-aware date tracking to prevent discrepancies in expiry calculations across regional distribution centers.
- **`jsonb` Metadata**: Stores confidence metrics and bounding-box coordinates from optical label analysis.

### Executing Migrations Manually
```bash
php artisan migrate:status
php artisan migrate --force
```

---

## 4. Authentication & Multi-Tenant Scoping

1. **Isolation Principle**: Every product record contains a mandatory foreign key `user_id` referencing the tenant workspace account.
2. **Global Scopes**: Eloquent models automatically constrain queries using:
```php
static::addGlobalScope('tenant', function (Builder $builder) {
    if (auth()->check()) {
        $builder->where('user_id', auth()->id());
    }
});
```
3. **Session & Token Auth**: The web dashboard utilizes secure, encrypted HTTP cookies with CSRF protection, while the mobile client communicates via Bearer tokens generated through Laravel Sanctum.

---

## 5. Automated Expiry Alerts & Scheduled Tasks

Stockki classifies inventory shelf-life into three operational states:
1. **Safe**: Greater than 30 days remaining until expiry.
2. **Expiring Soon**: 30 days or fewer remaining. Requires immediate promotional markdown or priority dispatch.
3. **Expired**: Zero days remaining or negative offset. Requires quarantine and regulated disposal.

### Configuring Automated Cron Job
In Linux/cPanel environments, register the scheduler in crontab:
```bash
* * * * * cd /path/to/stockki && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Hosting on cPanel (Complete Production Guide)

Hosting a modern PHP application on **cPanel** requires separating application logic from public assets to prevent public access to `.env` and source code.

### Step 6.1: Select PHP 8.3+ in cPanel
1. Log in to your cPanel dashboard.
2. Locate and open **MultiPHP Manager** or **Select PHP Version**.
3. Select your domain or subdomain.
4. Set the PHP Version to **PHP 8.3** (or highest available 8.x).
5. Open **MultiPHP INI Editor**, select your domain, and ensure:
   - `memory_limit = 256M`
   - `upload_max_filesize = 32M`
   - `post_max_size = 32M`
   - `max_execution_time = 120`

### Step 6.2: Directory Separation Strategy
Create a directory structure outside `public_html`:
```
/home/username/
├── stockki/               <-- Main application files (private)
│   ├── app/
│   ├── bootstrap/
│   ├── database/
│   ├── lang/
│   ├── .env
│   └── vendor/
└── public_html/          <-- Public web directory
    ├── index.php         <-- Modified bootstrap entry
    ├── assets/
    └── .htaccess
```

### Step 6.3: Uploading Files via File Manager or SSH
1. Compress your project (excluding `node_modules` and `vendor`).
2. Open cPanel **File Manager**.
3. Create a folder named `stockki` in `/home/username/`.
4. Upload and extract your project files into `/home/username/stockki/`.
5. Move the contents of `/home/username/stockki/public/` directly into `/home/username/public_html/`.

### Step 6.4: Adjust `/home/username/public_html/index.php`
Edit `public_html/index.php` to point to the parent application directory:
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/../stockki/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Autoloader
require __DIR__.'/../stockki/vendor/autoload.php';

// Bootstrap Application
(require_once __DIR__.'/../stockki/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

### Step 6.5: Configure Database in cPanel
1. Navigate to **MySQL Databases** (or **PostgreSQL Databases** if enabled by your host).
2. Create a new database: e.g., `username_stockki`.
3. Create a new database user: e.g., `username_stockkiuser` with a strong password.
4. Associate the user with the database and grant **ALL PRIVILEGES**.
5. Edit `/home/username/stockki/.env`:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=username_stockki
DB_USERNAME=username_stockkiuser
DB_PASSWORD=YourPasswordHere
```

### Step 6.6: Run Migrations on cPanel
**Option A: Using cPanel Terminal (SSH)**
```bash
cd /home/username/stockki
php artisan migrate --force
```

**Option B: Using a Temporary Migration Route or phpMyAdmin**
Export your local schema or execute SQL dump via cPanel **phpMyAdmin**.

### Step 6.7: Configure `.htaccess` for Clean Routing & HTTPS
Ensure `/home/username/public_html/.htaccess` contains:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Step 6.8: Set Up cPanel Cron Job for Expiry Alerts
1. In cPanel, click **Cron Jobs**.
2. Under "Add New Cron Job", select "Once Per Day" (or "Once Per Minute" for real-time queues).
3. Set the command:
```bash
/usr/local/bin/php /home/username/stockki/artisan schedule:run >> /dev/null 2>&1
```

### Step 6.9: Enable Free SSL (AutoSSL / Let's Encrypt)
1. Go to **SSL/TLS Status** in cPanel.
2. Select your domain and click **Run AutoSSL**.
3. Confirm the padlock is active on `https://yourdomain.com`.

---

## 7. Mobile App (Flutter 3.x) Setup

### Step 7.1: Configure API Base URL
In `mobile/lib/core/constants/api_endpoints.dart`:
```dart
class ApiEndpoints {
  static const String baseUrl = 'https://yourdomain.com/api/v1';
  static const String login = '$baseUrl/auth/login';
  static const String inventory = '$baseUrl/inventory';
  static const String recognition = '$baseUrl/recognition/analyze';
}
```

### Step 7.2: Build and Test
```bash
cd mobile
flutter pub get
flutter gen-l10n
flutter test
flutter build apk --release
```

---

## 8. Troubleshooting & Maintenance

| Symptom | Probable Cause | Resolution |
| :--- | :--- | :--- |
| **500 Internal Server Error** | Permissions or missing `.env` | Ensure storage is writable: `chmod -R 775 storage bootstrap/cache` |
| **Blank White Screen** | Incompatible PHP version | Switch domain to PHP 8.3 in MultiPHP Manager |
| **404 on Internal Routes** | Missing `mod_rewrite` rules | Verify `.htaccess` exists in `public_html/` |
| **Camera Permission Denied** | Insecure context (HTTP) | WebRTC requires HTTPS or localhost. Install SSL. |

---

## 9. Credits & Copyright

Desenvolvido por **Kaio Borges Dev** ([dev.kaioborges.com.br](https://dev.kaioborges.com.br/)) — **5E Group** ([@5ebrasil](https://instagram.com/5ebrasil)). Todos os direitos reservados.
