# School ERP SaaS - Project Guide

This project is a multi-tenant School ERP Solution. This README documents the latest system improvements and provides a comprehensive guide for production deployment.

---

## 🚀 Recent System Improvements (March 1, 2026)

We have completed a major infrastructure cleanup and automated the school provisioning process.
For a detailed guide on how these features work, see the [FEATURES.md](file:///d:/PROJECTS/YASHU_MITTAL/SCHOOL_ERP_SASS/FEATURES.md).

### 1. Cleanup & Stability
- **Removed `mint/service`**: The project is now fully decoupled from the `mint/service` dependency.
- **Autoloader Fix**: Resolved the "Target class [auth] does not exist" error by repairing a corrupted `vendor` directory and fixing namespace collisions.
- **Code Integrity**: Fixed several corrupted core files (`artisan`, `public/index.php`, `bootstrap/app.php`) that contained suspicious typos and non-standard strings.

### 2. School Provisioning Automation
The process of creating new schools has been simplified:
- **Hidden Technical Fields**: The "Database & Storage" section is now managed automatically in the background.
- **Auto-Generation**: `DB Name` and `Storage Folder` are generated derived from the School Name.
- **Env Integration**: Database credentials for new tenants are pulled directly from your `.env` file.
- **Admin Setup**: The admin email is automatically suggested based on the domain provided.

---

## 🛠 Deployment & Go-Live Guide

### 1. Environment Configuration (.env)
Ensure your production `.env` has these critical settings for the wildcard system:

```env
APP_NAME="Anohim - School ERP Solution"
APP_ENV=production
APP_URL=https://kalkix.site

# Database for Provisioning
# This user needs 'CREATE DATABASE' privileges
DB_USERNAME=kalkix_site
DB_PASSWORD=your_secure_password

# Wildcard & Session Support
CENTRAL_DOMAIN=governance.kalkix.site
SANCTUM_STATEFUL_DOMAINS="kalkix.site,governance.kalkix.site"
SESSION_DOMAIN=.kalkix.site
```

### 2. Nginx Setup (`kalkix.site` & `*.kalkix.site`)
Configure your server block to handle the main site and all school subdomains:

```nginx
server {
    listen 80;
    server_name kalkix.site *.kalkix.site;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name kalkix.site *.kalkix.site;
    root /var/www/SCHOOL_ERP_SASS/public;

    index index.php;
    ssl_certificate /etc/letsencrypt/live/kalkix.site/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/kalkix.site/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

### 3. SSL Setup (Wildcard DNS Challenge)
To support all dynamic school subdomains (`*.kalkix.site`), use the DNS challenge:
```bash
sudo certbot certonly --manual --preferred-challenges dns -d "kalkix.site" -d "*.kalkix.site"
```
*Add the provided TXT records to your DNS panel (Cloudflare, GoDaddy, etc.) to verify ownership.*

---

## ⚙️ Maintenance Commands
Run these after any code update:

```bash
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

---

## 🏗️ cPanel / Shared Hosting Deployment Guide

Deploying a multi-tenant SaaS application on cPanel requires specific configurations, primarily around **Wildcard Subdomains** and **Database Permissions**, allowing the core application to dynamically generate routing and databases for new schools.

### Step 1: File Structure
1. Zip your entire Laravel project.
2. Open **cPanel > File Manager**.
3. It is completely recommended to **NOT** place the Laravel project directly inside `public_html`.
   - Create a folder at the root of your cPanel account (e.g., `/home/username/school_erp_sass/`).
   - Extract your project here.
4. Go to `/home/username/school_erp_sass/public/`. Select all files inside this `public` folder and Move them to `/home/username/public_html/`.
5. Open `/home/username/public_html/index.php` and update the paths to point to the core Laravel files:
    ```php
    // Change these from:
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';

    // To:
    require __DIR__.'/../school_erp_sass/vendor/autoload.php';
    $app = require_once __DIR__.'/../school_erp_sass/bootstrap/app.php';
    ```

### Step 2: Wildcard Subdomains (Crucial for SaaS)
For tenants like `school1.kalkix.site`, `school2.kalkix.site` to work, the server must dynamically route any subdomain to the main application.

1. Go to **cPanel > Domains** (or *Subdomains* in older cPanels).
2. Create a new subdomain.
3. In the **Subdomain** field, type an asterisk: `*`
4. Select your main domain (`kalkix.site`).
5. **Document Root**: Point this to `public_html` (the exact same folder as your main domain).
6. Click **Create**.
*Note: You also need an A-Record in your DNS provider (Cloudflare/Namecheap) pointing `*` to your cPanel IP address.*

### Step 3: Database & Privileges
This app automates school database creation. To do this, your core database user needs high privileges.

1. Go to **cPanel > MySQL Databases**.
2. Create the Central Database (e.g., `kalkix_central`).
3. Create a Database User (e.g., `kalkix_admin`) and generate a strong password.
4. **Important**: When adding the user to the database, you must grant **ALL PRIVILEGES** (this includes `CREATE`, `DROP`, `ALTER`). The application uses these rights to dynamically create `school_abc` databases during the provisioning step.

### Step 4: `.env` Configuration
Open your `.env` file (located in `/home/username/school_erp_sass/.env`) and set the Live credentials:

```env
APP_NAME="Anohim - School ERP Solution"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kalkix.site

# Central Database (Created in Step 3)
DB_CONNECTION=central
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kalkix_central
# This User MUST have CREATE DATABASE privileges globally on the server
DB_USERNAME=kalkix_admin
DB_PASSWORD=your_secure_password

# Wildcard Setup
CENTRAL_DOMAIN=governance.kalkix.site
SANCTUM_STATEFUL_DOMAINS="kalkix.site,governance.kalkix.site"
SESSION_DOMAIN=.kalkix.site
```

### Step 5: Finalization via SSH (Terminal)
Most modern cPanels provide SSH access. Open **cPanel > Terminal**:
```bash
cd school_erp_sass

# Clear all caches
php artisan optimize:clear

# Run initial migrations & seeders (Make sure to seed!)
php artisan migrate --force
php artisan db:seed --force

# Link storage (So tenant logos & media work)
# If storage:link fails, you may need to manually create a symlink from public_html/storage to school_erp_sass/storage/app/public
php artisan storage:link
```

Your School ERP SaaS is now live on cPanel! You can access the governance panel at `governance.yourdomain.com/app`.

---
*Documentation maintained by Antigravity AI.*
