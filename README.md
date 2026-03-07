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
*Documentation maintained by Antigravity AI.*
