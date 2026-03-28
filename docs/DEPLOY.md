# MRTG Manager – Deployment Guide

**Project:** NOC MRTG Manager  
**Version:** 1.0.0  
**Server:** 10.123.123.202 (noc-mrtg)  
**Last Updated:** 2025-01-01

---

## Table of Contents

1. [Apache VirtualHost Configuration](#1-apache-virtualhost-configuration)
2. [Environment Configuration](#2-environment-configuration)
3. [Crontab Entries](#3-crontab-entries)
4. [File Permissions Setup](#4-file-permissions-setup)
5. [UFW Firewall Rules](#5-ufw-firewall-rules)
6. [SSL/TLS Setup Guide](#6-ssltls-setup-guide)
7. [Log Rotation](#7-log-rotation)
8. [MariaDB Backup Strategy](#8-mariadb-backup-strategy)
9. [Deployment Checklist](#9-deployment-checklist)
10. [Troubleshooting Guide](#10-troubleshooting-guide)

---

## 1. Apache VirtualHost Configuration

### 1.1 HTTP VirtualHost (`/etc/apache2/sites-available/noc-manager.conf`)

```apache
<VirtualHost *:80>
    ServerName localhost
    ServerAlias noc.triraintiutama.local

    DocumentRoot /var/www/noc/public

    # PHP handler via libapache2-mod-php8.3
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    # Main application directory
    <Directory /var/www/noc/public>
        Options -Indexes -MultiViews +SymLinksIfOwnerMatch
        AllowOverride All
        Require all granted

        # Hardened security headers
        Header always set X-Content-Type-Options    "nosniff"
        Header always set X-Frame-Options           "SAMEORIGIN"
        Header always set X-XSS-Protection          "1; mode=block"
        Header always set Referrer-Policy           "strict-origin-when-cross-origin"
        Header always set Permissions-Policy        "geolocation=(), microphone=(), camera=()"
        Header always set Cache-Control             "no-store, no-cache, must-revalidate"
    </Directory>

    # MRTG graph output – read-only, no PHP execution
    Alias /mrtg /var/www/mrtg
    <Directory /var/www/mrtg>
        Options -Indexes -ExecCGI
        AllowOverride None
        Require all granted
        <FilesMatch \.php$>
            Require all denied
        </FilesMatch>
    </Directory>

    # Block direct web access to application internals
    <LocationMatch "^/(core|config|database|cron|snmp|modules)">
        Require all denied
    </LocationMatch>

    # Block .env and hidden files
    <FilesMatch "(^\.env|\.git|\.htpasswd|composer\.json|composer\.lock)$">
        Require all denied
    </FilesMatch>

    # PHP runtime settings for this vhost
    php_value  error_log             /var/log/noc/php_error.log
    php_flag   display_errors        off
    php_flag   log_errors            on
    php_value  session.name          noc_session
    php_value  session.cookie_samesite Strict
    php_flag   session.cookie_httponly on

    # Logging
    ErrorLog  /var/log/apache2/noc-error.log
    CustomLog /var/log/apache2/noc-access.log combined

    # Deflate compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
        AddOutputFilterByType DEFLATE application/javascript application/json
        AddOutputFilterByType DEFLATE image/svg+xml
        BrowserMatch ^Mozilla/4   gzip-only-text/html
        BrowserMatch ^Mozilla/4\.0[678]  no-gzip
        BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    </IfModule>

    # Static asset caching
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/png         "access plus 7 days"
        ExpiresByType image/gif         "access plus 7 days"
        ExpiresByType image/jpeg        "access plus 7 days"
        ExpiresByType text/css          "access plus 1 day"
        ExpiresByType application/javascript "access plus 1 day"
        ExpiresByType image/x-icon      "access plus 30 days"
    </IfModule>
</VirtualHost>
```

### 1.2 Enable the VirtualHost

```bash
a2ensite noc-manager.conf
a2dissite 000-default.conf
apache2ctl configtest && systemctl reload apache2
```

### 1.3 Required Apache Modules

```bash
a2enmod rewrite headers ssl expires deflate
systemctl restart apache2
```

Verify all modules are loaded:

```bash
apache2ctl -M | grep -E 'rewrite|headers|ssl|expires|deflate'
```

### 1.4 Public .htaccess (`/var/www/noc/public/.htaccess`)

```apache
# Disable directory listings
Options -Indexes

# Enable URL rewriting
RewriteEngine On

# Uncomment for HTTPS redirect after SSL setup:
# RewriteCond %{HTTPS} off
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route all requests through the front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]

# Deny access to dotfiles
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Set charset
AddDefaultCharset UTF-8

# Prevent MIME type sniffing
Header always set X-Content-Type-Options "nosniff"
```

---

## 2. Environment Configuration

### 2.1 Production `.env` (`/var/www/noc/config/.env`)

```ini
# ============================================================
# NOC MRTG Manager – Production Environment Configuration
# ============================================================
# SECURITY: This file contains secrets. Permissions: 640
# Owner: root  |  Group: www-data
# NEVER commit this file to version control.
# ============================================================

# ----- Application -----
APP_NAME="NOC MRTG Manager"
APP_VERSION=1.0.0
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_SECRET=Bismillah411BerkahTriraIntiUtama
APP_DIR=/var/www/noc
APP_TIMEZONE=Asia/Jakarta

# ----- Database -----
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=noc_manager
DB_USER=noc_user
DB_PASS=Pusing7Keliling
DB_CHARSET=utf8mb4

# ----- MRTG -----
MRTG_DIR=/var/www/mrtg
MRTG_CFG=/etc/mrtg
MRTG_BIN=/usr/bin/mrtg
CFGMAKER_BIN=/usr/bin/cfgmaker
INDEXMAKER_BIN=/usr/bin/indexmaker

# ----- SNMP Defaults -----
SNMP_TIMEOUT=3000000
SNMP_RETRIES=2
SNMP_VERSION=2c
SNMP_COMMUNITY=public

# ----- Logging -----
LOG_DIR=/var/log/noc
LOG_LEVEL=info

# ----- Security -----
SESSION_LIFETIME=1800
SESSION_NAME=noc_session
CSRF_TOKEN_EXPIRY=3600
MAX_LOGIN_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
BCRYPT_COST=12

# ----- Data Retention -----
RETENTION_RAW_HOURS=72
RETENTION_DAILY_DAYS=365
RETENTION_WEEKLY_WEEKS=104
RETENTION_MONTHLY_MONTHS=60
```

### 2.2 Secure the Environment File

```bash
chown root:www-data /var/www/noc/config/.env
chmod 640 /var/www/noc/config/.env

# Verify it is not web-accessible
curl -s -o /dev/null -w "%{http_code}" http://localhost/config/.env
# Expected: 404 (blocked by .htaccess LocationMatch rule)
```

### 2.3 `.env.example` (safe to commit to version control)

```bash
cp /var/www/noc/config/.env /var/www/noc/config/.env.example

# Redact sensitive values in .env.example
sed -i \
    -e 's/APP_SECRET=.*/APP_SECRET=change-me-in-production/' \
    -e 's/DB_PASS=.*/DB_PASS=change-me/' \
    -e 's/SNMP_COMMUNITY=.*/SNMP_COMMUNITY=public/' \
    /var/www/noc/config/.env.example
```

---

## 3. Crontab Entries

### 3.1 System Crontab File (`/etc/cron.d/noc-manager`)

```cron
# NOC MRTG Manager – Scheduled Tasks
# Managed by: system administrator
# Last updated: 2025-01-01
#
# All tasks run as www-data to match Apache process owner.
# Output is appended to log files in /var/log/noc/
#
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""
HOME=/var/www/noc

# ── MRTG Polling ─────────────────────────────────────────────────────────────
# Run MRTG every 5 minutes for all router configs.
# MRTG reads /etc/mrtg/mrtg.cfg which includes all router_*.cfg files.
# Generates PNG graphs and .log data files in /var/www/mrtg/
*/5 * * * *   www-data   /usr/bin/mrtg /etc/mrtg/mrtg.cfg --logging /var/log/noc/mrtg.log 2>&1

# ── SNMP Database Polling ─────────────────────────────────────────────────────
# Poll all monitored routers via SNMP and store raw traffic counters in MariaDB.
# This feeds the traffic_data table for Chart.js graphs.
*/5 * * * *   www-data   /usr/bin/php8.3 /var/www/noc/cron/poll_snmp.php >> /var/log/noc/snmp.log 2>&1

# ── Hourly Aggregation ────────────────────────────────────────────────────────
# Aggregate 5-minute traffic_data rows into hourly summaries.
# Runs at minute 1 to avoid conflict with the 5-minute pollers.
1 * * * *     www-data   /usr/bin/php8.3 /var/www/noc/cron/aggregate_hourly.php >> /var/log/noc/app.log 2>&1

# ── Daily Aggregation ─────────────────────────────────────────────────────────
# Aggregate hourly data into traffic_daily table.
# Runs at 00:05 daily (after midnight, after hourly agg completes).
5 0 * * *     www-data   /usr/bin/php8.3 /var/www/noc/cron/aggregate_daily.php >> /var/log/noc/app.log 2>&1

# ── Weekly Aggregation ────────────────────────────────────────────────────────
# Aggregate daily data into traffic_weekly table.
# Runs Monday at 00:10.
10 0 * * 1    www-data   /usr/bin/php8.3 /var/www/noc/cron/aggregate_weekly.php >> /var/log/noc/app.log 2>&1

# ── Monthly Aggregation ───────────────────────────────────────────────────────
# Aggregate daily data into traffic_monthly table.
# Runs 1st of month at 00:15.
15 0 1 * *    www-data   /usr/bin/php8.3 /var/www/noc/cron/aggregate_weekly.php >> /var/log/noc/app.log 2>&1

# ── Data Cleanup ──────────────────────────────────────────────────────────────
# Purge raw traffic_data older than RETENTION_RAW_HOURS (default 72h).
# Runs daily at 02:00.
0 2 * * *     www-data   /usr/bin/php8.3 /var/www/noc/cron/cleanup.php >> /var/log/noc/app.log 2>&1
```

### 3.2 Apply and Verify Crontab

```bash
# Set correct permissions
chmod 644 /etc/cron.d/noc-manager
chown root:root /etc/cron.d/noc-manager

# Reload cron daemon
systemctl reload cron

# Watch cron execution in syslog
tail -f /var/log/syslog | grep -i cron
```

### 3.3 Manual Test of Cron Scripts

```bash
# Test SNMP polling script manually
sudo -u www-data /usr/bin/php8.3 /var/www/noc/cron/poll_snmp.php

# Test cleanup script
sudo -u www-data /usr/bin/php8.3 /var/www/noc/cron/cleanup.php

# Test MRTG manually (run twice to generate meaningful graphs)
sudo -u www-data /usr/bin/mrtg /etc/mrtg/mrtg.cfg
sleep 10
sudo -u www-data /usr/bin/mrtg /etc/mrtg/mrtg.cfg
```

---

## 4. File Permissions Setup

### 4.1 Quick Permissions Reset Script

Run this script if permissions become misconfigured:

```bash
#!/bin/bash
# reset-permissions.sh – Reset NOC MRTG Manager file permissions
set -e

APP=/var/www/noc
MRTG=/var/www/mrtg
MRTG_CFG=/etc/mrtg
LOGS=/var/log/noc

echo "[1/5] Setting ownership..."
chown -R www-data:www-data "$APP"
chown -R www-data:www-data "$MRTG"
chown -R www-data:www-data "$MRTG_CFG"
chown -R www-data:www-data "$LOGS"

# Config/.env owned by root, readable by www-data
chown root:www-data "$APP/config/.env"

echo "[2/5] Setting directory permissions..."
find "$APP/public"  -type d -exec chmod 755 {} \;
find "$APP/core"    -type d -exec chmod 750 {} \;
find "$APP/modules" -type d -exec chmod 750 {} \;
find "$APP/api"     -type d -exec chmod 750 {} \;
find "$APP/cron"    -type d -exec chmod 750 {} \;
find "$APP/config"  -type d -exec chmod 750 {} \;
find "$MRTG"        -type d -exec chmod 755 {} \;
find "$MRTG_CFG"    -type d -exec chmod 750 {} \;
chmod 750 "$LOGS"

echo "[3/5] Setting file permissions..."
find "$APP/public"  -type f -exec chmod 644 {} \;
find "$APP/core"    -type f -exec chmod 640 {} \;
find "$APP/modules" -type f -exec chmod 640 {} \;
find "$APP/api"     -type f -exec chmod 640 {} \;
find "$APP/cron"    -type f -exec chmod 750 {} \;
chmod 640 "$APP/config/.env"
find "$MRTG"        -type f -exec chmod 644 {} \;
find "$MRTG_CFG" -name '*.cfg' -exec chmod 640 {} \;
find "$LOGS"    -name '*.log' -exec chmod 640 {} \;
chmod 440 "$APP/database"/*.sql 2>/dev/null || true

echo "[4/5] Securing sensitive files..."
chmod 640 "$APP/config/.env"
chown root:www-data "$APP/config/.env"

echo "[5/5] Done. Current permissions:"
ls -la "$APP/config/"
ls -la "$MRTG_CFG/"
echo "Permissions reset complete."
```

### 4.2 Permission Reference Table

| Path | Owner | Group | Mode | Notes |
|------|-------|-------|------|-------|
| `/var/www/noc/` | www-data | www-data | 755 | App root |
| `/var/www/noc/public/` | www-data | www-data | 755 | Web root |
| `/var/www/noc/public/*.php` | www-data | www-data | 644 | PHP files |
| `/var/www/noc/core/` | www-data | www-data | 750 | Core library |
| `/var/www/noc/modules/` | www-data | www-data | 750 | Module dir |
| `/var/www/noc/api/` | www-data | www-data | 750 | API dir |
| `/var/www/noc/cron/` | www-data | www-data | 750 | Cron scripts |
| `/var/www/noc/config/` | www-data | www-data | 750 | Config dir |
| `/var/www/noc/config/.env` | root | www-data | 640 | Secret env |
| `/var/www/noc/database/*.sql` | www-data | www-data | 440 | SQL files |
| `/var/www/mrtg/` | www-data | www-data | 755 | MRTG output |
| `/etc/mrtg/` | www-data | www-data | 750 | MRTG config |
| `/etc/mrtg/*.cfg` | www-data | www-data | 640 | MRTG config files |
| `/var/log/noc/` | www-data | www-data | 750 | Log dir |
| `/var/log/noc/*.log` | www-data | www-data | 640 | Log files |

---

## 5. UFW Firewall Rules

### 5.1 Complete UFW Configuration

```bash
# Reset to defaults
ufw --force reset

# Default policies
ufw default deny incoming
ufw default allow outgoing
ufw default deny forward

# Allow SSH (from anywhere – tighten to management IP if possible)
ufw allow 22/tcp comment 'SSH management'

# Allow HTTP
ufw allow 80/tcp comment 'HTTP web interface'

# Allow HTTPS (prepare for SSL)
ufw allow 443/tcp comment 'HTTPS web interface'

# Block direct SNMP access from internet (SNMP is outbound-only)
# SNMP responses are handled by stateful tracking – no inbound rule needed

# Rate-limit SSH to prevent brute-force
ufw limit 22/tcp comment 'SSH rate-limit'

# Enable firewall
ufw --force enable
```

### 5.2 Restrict to Management Network (Recommended)

```bash
# Allow SSH only from NOC management subnet
ufw delete allow 22/tcp
ufw allow from 10.123.123.0/24 to any port 22 proto tcp comment 'SSH management subnet'

# Allow web interface from LAN only (if not public-facing)
# ufw delete allow 80/tcp
# ufw allow from 10.123.123.0/24 to any port 80 proto tcp comment 'HTTP LAN only'
```

### 5.3 Verify Rules

```bash
ufw status verbose
ufw status numbered
```

Expected final ruleset:
```
Status: active
Logging: on (low)
Default: deny (incoming), allow (outgoing), deny (forward)

     To                         Action      From
     --                         ------      ----
[ 1] 22/tcp                     LIMIT IN    Anywhere
[ 2] 80/tcp                     ALLOW IN    Anywhere
[ 3] 443/tcp                    ALLOW IN    Anywhere
```

### 5.4 Block Common Attack Vectors

```bash
# Block Null packets
iptables -A INPUT -p tcp --tcp-flags ALL NONE -j DROP

# Block XMAS packets
iptables -A INPUT -p tcp --tcp-flags ALL ALL -j DROP

# These rules are lost on reboot; use ufw or persist via iptables-save
# For persistence via ufw:
cat >> /etc/ufw/before.rules << 'EOF'

# Block NULL packets
-A ufw-before-input -p tcp --tcp-flags ALL NONE -j DROP

# Block XMAS packets
-A ufw-before-input -p tcp --tcp-flags ALL ALL -j DROP
EOF

ufw reload
```

---

## 6. SSL/TLS Setup Guide

### 6.1 Option A: Self-Signed Certificate (Internal Use)

```bash
# Create SSL directory
mkdir -p /etc/apache2/ssl

# Generate self-signed certificate (valid for 3 years)
openssl req -x509 -nodes -days 1095 -newkey rsa:4096 \
    -keyout /etc/apache2/ssl/noc-manager.key \
    -out    /etc/apache2/ssl/noc-manager.crt \
    -subj "/C=ID/ST=Jawa Timur/L=Surabaya/O=Trira Inti Utama/CN=noc.triraintiutama.local" \
    -addext "subjectAltName=DNS:noc.triraintiutama.local,DNS:localhost,IP:10.123.123.202"

# Secure key file
chmod 600 /etc/apache2/ssl/noc-manager.key
chmod 644 /etc/apache2/ssl/noc-manager.crt
chown root:root /etc/apache2/ssl/noc-manager.key
```

### 6.2 Create HTTPS VirtualHost

```bash
cat > /etc/apache2/sites-available/noc-manager-ssl.conf << 'EOF'
<VirtualHost *:443>
    ServerName localhost
    ServerAlias noc.triraintiutama.local

    DocumentRoot /var/www/noc/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile    /etc/apache2/ssl/noc-manager.crt
    SSLCertificateKeyFile /etc/apache2/ssl/noc-manager.key

    # Modern SSL settings (TLS 1.2+ only)
    SSLProtocol             all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite          ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder     off
    SSLSessionTickets       off

    # HSTS
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains"

    # PHP handler
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    <Directory /var/www/noc/public>
        Options -Indexes +SymLinksIfOwnerMatch
        AllowOverride All
        Require all granted

        Header always set X-Content-Type-Options    "nosniff"
        Header always set X-Frame-Options           "SAMEORIGIN"
        Header always set X-XSS-Protection          "1; mode=block"
        Header always set Referrer-Policy           "strict-origin-when-cross-origin"
    </Directory>

    Alias /mrtg /var/www/mrtg
    <Directory /var/www/mrtg>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    <LocationMatch "^/(core|config|database|cron|snmp|modules)">
        Require all denied
    </LocationMatch>

    php_value  error_log   /var/log/noc/php_error.log
    php_flag   display_errors  off

    ErrorLog  /var/log/apache2/noc-ssl-error.log
    CustomLog /var/log/apache2/noc-ssl-access.log combined
</VirtualHost>
EOF
```

### 6.3 Update HTTP VirtualHost to Redirect to HTTPS

```bash
# Replace the HTTP VirtualHost with a redirect
cat > /etc/apache2/sites-available/noc-manager.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    ServerAlias noc.triraintiutama.local

    # Redirect all HTTP to HTTPS
    RewriteEngine On
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]

    ErrorLog  /var/log/apache2/noc-error.log
    CustomLog /var/log/apache2/noc-access.log combined
</VirtualHost>
EOF
```

### 6.4 Enable SSL Site

```bash
a2enmod ssl
a2ensite noc-manager-ssl.conf
apache2ctl configtest && systemctl reload apache2
```

### 6.5 Update .env for HTTPS

```bash
sed -i 's|APP_URL=http://localhost|APP_URL=https://localhost|' \
    /var/www/noc/config/.env
```

### 6.6 Update Cookie Settings for HTTPS

```bash
sed -i 's/session.cookie_secure = 0/session.cookie_secure = 1/' \
    /etc/php/8.3/apache2/conf.d/99-noc.ini
systemctl reload apache2
```

### 6.7 Option B: Let's Encrypt (Public DNS Required)

```bash
# Only applicable if server is publicly reachable with valid DNS
apt install -y certbot python3-certbot-apache

certbot --apache \
    -d noc.triraintiutama.local \
    --email admin@triraintiutama.local \
    --agree-tos \
    --no-eff-email

# Auto-renewal is configured by certbot in /etc/cron.d/certbot
```

---

## 7. Log Rotation

### 7.1 Create Logrotate Configuration

```bash
cat > /etc/logrotate.d/noc-manager << 'EOF'
/var/log/noc/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 www-data www-data
    sharedscripts
    postrotate
        # Signal Apache to reopen log files
        /usr/bin/systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}

/var/log/apache2/noc-*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 640 www-data adm
    sharedscripts
    postrotate
        /usr/bin/systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
EOF
```

### 7.2 Test Log Rotation

```bash
logrotate -d /etc/logrotate.d/noc-manager
logrotate --force /etc/logrotate.d/noc-manager
```

---

## 8. MariaDB Backup Strategy

### 8.1 Automated Daily Backup Script

```bash
mkdir -p /var/backups/noc-db
chown root:root /var/backups/noc-db
chmod 700 /var/backups/noc-db

cat > /usr/local/bin/noc-db-backup.sh << 'SCRIPT'
#!/bin/bash
# NOC MRTG Manager – MariaDB Backup Script
BACKUP_DIR=/var/backups/noc-db
RETENTION_DAYS=14
DB_NAME=noc_manager
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/noc_manager_${TIMESTAMP}.sql.gz"

# Dump and compress
mariadb-dump \
    --single-transaction \
    --routines \
    --triggers \
    --skip-lock-tables \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$BACKUP_FILE"

# Verify backup was created
if [ -s "$BACKUP_FILE" ]; then
    echo "$(date): Backup created: $BACKUP_FILE ($(du -sh "$BACKUP_FILE" | cut -f1))"
else
    echo "$(date): ERROR - Backup failed or empty: $BACKUP_FILE" >&2
    exit 1
fi

# Purge old backups
find "$BACKUP_DIR" -name 'noc_manager_*.sql.gz' \
    -mtime +"$RETENTION_DAYS" -delete

echo "$(date): Backup complete."
SCRIPT

chmod 700 /usr/local/bin/noc-db-backup.sh
```

### 8.2 Add to Crontab

```bash
cat >> /etc/cron.d/noc-manager << 'EOF'

# Database backup – daily at 03:00
0 3 * * *     root      /usr/local/bin/noc-db-backup.sh >> /var/log/noc/backup.log 2>&1
EOF
```

### 8.3 Test Backup

```bash
/usr/local/bin/noc-db-backup.sh
ls -lh /var/backups/noc-db/
```

### 8.4 Restore from Backup

```bash
# List available backups
ls -lt /var/backups/noc-db/

# Restore (replace TIMESTAMP with actual value)
zcat /var/backups/noc-db/noc_manager_TIMESTAMP.sql.gz | \
    mariadb -u root -p noc_manager
```

---

## 9. Deployment Checklist

### 9.1 Pre-Deployment

- [ ] Ubuntu 24.04 LTS base system updated (`apt upgrade -y`)
- [ ] Hostname set to `noc-mrtg`
- [ ] `/etc/hosts` updated with `10.123.123.202 noc.triraintiutama.local`
- [ ] Timezone set to `Asia/Jakarta`

### 9.2 Services

- [ ] Apache 2.4 installed and running: `systemctl status apache2`
- [ ] PHP 8.3 installed with all extensions: `php8.3 -m | grep -E 'pdo_mysql|snmp|mbstring'`
- [ ] MariaDB 11 installed and running: `systemctl status mariadb`
- [ ] MRTG installed: `mrtg --version`
- [ ] Net-SNMP tools installed: `snmpwalk --version`

### 9.3 Application

- [ ] Application files in `/var/www/noc/`
- [ ] `/var/www/noc/config/.env` created with production values
- [ ] Database schema imported: `mariadb noc_manager < schema.sql`
- [ ] Seed data imported: `mariadb noc_manager < seed.sql`
- [ ] MRTG directories created and writable
- [ ] Log directory created at `/var/log/noc/`
- [ ] Symlinks in place (`/var/www/noc/logs`, `/var/www/noc/mrtg`, `/var/www/noc/public/mrtg`)

### 9.4 Configuration

- [ ] Apache VirtualHost enabled and tested: `apache2ctl configtest`
- [ ] PHP config applied: `/etc/php/8.3/apache2/conf.d/99-noc.ini`
- [ ] MariaDB config applied: `/etc/mysql/conf.d/noc-manager.cnf`
- [ ] MRTG global config: `/etc/mrtg/mrtg.cfg`
- [ ] Cron jobs active: `cat /etc/cron.d/noc-manager`

### 9.5 Security

- [ ] UFW enabled with correct rules: `ufw status verbose`
- [ ] `.env` file permissions: `ls -la /var/www/noc/config/.env` → `-rw-r----- root www-data`
- [ ] Admin password changed from default `Admin@123`
- [ ] `disable_functions` set in `/etc/php/8.3/apache2/conf.d/99-noc.ini`
- [ ] Directory listing disabled: `curl http://localhost/core/` → 403 Forbidden

### 9.6 Functionality Tests

- [ ] Login page loads: `curl -s -o /dev/null -w "%{http_code}" http://localhost/` → 200
- [ ] Dashboard accessible after login
- [ ] SNMP test to at least one router succeeds
- [ ] MRTG generates graphs for at least one router
- [ ] Traffic data stored in `traffic_data` table after one cron cycle

---

## 10. Troubleshooting Guide

### 10.1 Apache Issues

**Problem:** `apache2ctl configtest` fails  
**Solution:**
```bash
apache2ctl configtest 2>&1
# Look for "Syntax error" lines
# Common fix: missing quotes, wrong path, undefined variable
journalctl -u apache2 -n 50
```

**Problem:** 403 Forbidden on all pages  
**Solution:**
```bash
ls -la /var/www/noc/public/
# Ensure www-data has read access
chmod 755 /var/www/noc/public
chown -R www-data:www-data /var/www/noc/public
```

**Problem:** 500 Internal Server Error  
**Solution:**
```bash
tail -50 /var/log/apache2/noc-error.log
tail -50 /var/log/noc/php_error.log
# Enable debug mode temporarily:
# In /var/www/noc/config/.env: APP_DEBUG=true
# Remember to set back to false after debugging
```

**Problem:** mod_rewrite not working (.htaccess ignored)  
**Solution:**
```bash
apache2ctl -M | grep rewrite
a2enmod rewrite
# Ensure AllowOverride All is in VirtualHost Directory block
grep -n 'AllowOverride' /etc/apache2/sites-available/noc-manager.conf
systemctl restart apache2
```

---

### 10.2 PHP Issues

**Problem:** `Call to undefined function snmpwalk()`  
**Solution:**
```bash
php8.3 -m | grep snmp
apt install php8.3-snmp
phpenmod snmp
systemctl restart apache2
```

**Problem:** PDO connection fails  
**Solution:**
```bash
php8.3 -m | grep pdo
apt install php8.3-mysql
phpenmod pdo_mysql
systemctl restart apache2

# Test connection directly
php8.3 -r "new PDO('mysql:host=127.0.0.1;dbname=noc_manager', 'noc_user', 'Pusing7Keliling');"
```

**Problem:** Session not persisting  
**Solution:**
```bash
ls -la /var/lib/php/sessions/
chown www-data:www-data /var/lib/php/sessions
chmod 700 /var/lib/php/sessions
# Verify session settings in /etc/php/8.3/apache2/conf.d/99-noc.ini
```

---

### 10.3 MariaDB Issues

**Problem:** Access denied for `noc_user@127.0.0.1`  
**Solution:**
```bash
mariadb -u root -p -e "SELECT User, Host, Password FROM mysql.user WHERE User='noc_user';"
# If empty:
mariadb -u root -p -e "
CREATE USER 'noc_user'@'127.0.0.1' IDENTIFIED BY 'Pusing7Keliling';
GRANT SELECT,INSERT,UPDATE,DELETE ON noc_manager.* TO 'noc_user'@'127.0.0.1';
FLUSH PRIVILEGES;"
```

**Problem:** `ERROR 1045 (28000): Access denied for user 'root'@'localhost'`  
**Solution:**
```bash
# Connect via unix socket
sudo mariadb -u root
# Or reset root password via safe mode
systemctl stop mariadb
mysqld_safe --skip-grant-tables &
mariadb -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'new-root-password';"
```

**Problem:** MariaDB not listening on 127.0.0.1  
**Solution:**
```bash
ss -tlnp | grep 3306
grep bind-address /etc/mysql/conf.d/noc-manager.cnf
# Must be: bind-address = 127.0.0.1
systemctl restart mariadb
```

---

### 10.4 MRTG Issues

**Problem:** MRTG fails with "ERROR: Parsing config time"  
**Solution:**
```bash
# Check config syntax
mrtg /etc/mrtg/router_1.cfg --check
# Common causes: bad OID, unreachable host, wrong community string
```

**Problem:** MRTG not generating graphs  
**Solution:**
```bash
# Run manually as www-data and check output
sudo -u www-data /usr/bin/mrtg /etc/mrtg/router_1.cfg --logging /dev/stdout

# Check permissions on output directory
ls -la /var/www/mrtg/
chown -R www-data:www-data /var/www/mrtg
```

**Problem:** MRTG config file not writable  
**Solution:**
```bash
ls -la /etc/mrtg/
chown www-data:www-data /etc/mrtg
chmod 750 /etc/mrtg
```

---

### 10.5 SNMP Issues

**Problem:** `snmpwalk` returns no results  
**Solution:**
```bash
# Test basic SNMP connectivity
snmpwalk -v2c -c public -t 5 -r 2 <router-ip> .1.3.6.1.2.1.1.1.0

# If timeout – check router SNMP config and firewall
# On MikroTik: /snmp print
# Ensure community string matches and IP filter allows NOC server

# Test with verbose output
snmpwalk -v2c -c public -d <router-ip> sysDescr 2>&1 | head -30
```

**Problem:** PHP `snmp2_walk()` returns `false`  
**Solution:**
```bash
# Check PHP error log for SNMP errors
tail -20 /var/log/noc/php_error.log

# Verify SNMP extension is loaded
php8.3 -r "var_dump(function_exists('snmp2_walk'));"

# Test with snmpwalk CLI first to isolate if it's a PHP or network issue
```

**Problem:** Counter wrapping causing negative deltas  
**Solution:**
- Use `ifHCInOctets` / `ifHCOutOctets` (64-bit) instead of `ifInOctets` (32-bit)
- 32-bit counters wrap at 4,294,967,295 bytes (~4.3 GB) on high-speed interfaces
- The `poll_snmp.php` script should detect and handle wraps:
  ```php
  if ($current < $previous) {
      // 32-bit wrap: $delta = (PHP_INT_MAX_32 - $previous) + $current + 1;
      $delta = (4294967295 - $previous) + $current + 1;
  }
  ```

---

### 10.6 Cron Issues

**Problem:** Cron jobs not running  
**Solution:**
```bash
# Check cron daemon
systemctl status cron

# Check syslog for cron execution
grep CRON /var/log/syslog | tail -20

# Verify cron file syntax (no tabs before SHELL=)
cat -A /etc/cron.d/noc-manager | head -5

# Test as www-data manually
sudo -u www-data /usr/bin/php8.3 /var/www/noc/cron/poll_snmp.php
```

**Problem:** Cron runs but PHP script fails silently  
**Solution:**
```bash
# Redirect stderr in cron – already done with 2>&1 in our config
# Check log files
tail -50 /var/log/noc/snmp.log
tail -50 /var/log/noc/app.log

# Run with explicit error display
sudo -u www-data php8.3 -d display_errors=1 /var/www/noc/cron/poll_snmp.php
```

---

### 10.7 Performance Issues

**Problem:** Dashboard loads slowly  
**Solution:**
```bash
# Check slow query log
tail -50 /var/log/mysql/mariadb-slow.log

# Check missing indexes
mariadb -u root -p noc_manager -e "
EXPLAIN SELECT * FROM traffic_data 
WHERE target_type='interface' AND target_id=1 
ORDER BY timestamp DESC LIMIT 100;"

# Check PHP OPcache status
php8.3 -r "print_r(opcache_get_status());"
```

**Problem:** SNMP polling taking too long (> 5 minutes)  
**Solution:**
- Reduce `SNMP_TIMEOUT` from 3,000,000 to 1,500,000 in `.env`
- Reduce `SNMP_RETRIES` from 2 to 1
- Consider disabling monitoring for offline interfaces
- Run polling for different routers in parallel (use `pcntl_fork()` or batch into separate cron entries)

---

### 10.8 Health Check Command

Run this one-liner to verify all components are operational:

```bash
echo "=== Apache ===" && systemctl is-active apache2 \
  && echo "=== PHP ===" && php8.3 -r "echo 'PHP OK: ' . PHP_VERSION . PHP_EOL;" \
  && echo "=== MariaDB ===" && mariadb -u noc_user -p'Pusing7Keliling' -h 127.0.0.1 noc_manager \
       -e "SELECT 'MariaDB OK' AS status, COUNT(*) AS tables FROM information_schema.tables WHERE table_schema='noc_manager';" \
  && echo "=== MRTG ===" && mrtg --version 2>&1 | head -1 \
  && echo "=== UFW ===" && ufw status | head -3 \
  && echo "=== Cron ===" && ls /etc/cron.d/noc-manager \
  && echo "=== Web ===" && curl -s -o /dev/null -w "HTTP %{http_code}" http://localhost/ \
  && echo "" && echo "All checks passed."
```
