# MRTG Manager – Installation Guide

**Target OS:** Ubuntu 24.04 LTS  
**Server IP:** 10.123.123.202  
**Hostname:** noc-mrtg  
**Last Updated:** 2025-01-01

> **Important:** Run all commands as `root` or with `sudo` unless otherwise noted.  
> Commands prefixed with `$` can run as a normal user; those prefixed with `#` require root.

---

## Table of Contents

1. [System Preparation](#1-system-preparation)
2. [Install Apache 2.4](#2-install-apache-24)
3. [Install PHP 8.3](#3-install-php-83)
4. [Install MariaDB 11](#4-install-mariadb-11)
5. [Install MRTG & SNMP Tools](#5-install-mrtg--snmp-tools)
6. [Configure PHP](#6-configure-php)
7. [Configure Apache VirtualHost](#7-configure-apache-virtualhost)
8. [Configure MariaDB](#8-configure-mariadb)
9. [Deploy Application Files](#9-deploy-application-files)
10. [Configure Application Environment](#10-configure-application-environment)
11. [Initialize Database](#11-initialize-database)
12. [Configure MRTG](#12-configure-mrtg)
13. [Set Up Cron Jobs](#13-set-up-cron-jobs)
14. [Configure UFW Firewall](#14-configure-ufw-firewall)
15. [Set File Permissions](#15-set-file-permissions)
16. [Verify Installation](#16-verify-installation)

---

## 1. System Preparation

### 1.1 Set Hostname and Timezone

```bash
# Set server hostname
hostnamectl set-hostname noc-mrtg

# Verify
hostnamectl status
```

```bash
# Set timezone to Jakarta (WIB, UTC+7)
timedatectl set-timezone Asia/Jakarta

# Verify
timedatectl status
```

### 1.2 Update /etc/hosts

```bash
cat >> /etc/hosts << 'EOF'
10.123.123.202  noc-mrtg noc-mrtg.local noc.triraintiutama.local
EOF
```

### 1.3 Update System Packages

```bash
apt update && apt upgrade -y
```

### 1.4 Install Essential Utilities

```bash
apt install -y \
    curl \
    wget \
    git \
    unzip \
    vim \
    htop \
    net-tools \
    iputils-ping \
    dnsutils \
    software-properties-common \
    gnupg2 \
    lsb-release \
    ca-certificates \
    apt-transport-https
```

---

## 2. Install Apache 2.4

### 2.1 Install Apache

```bash
apt install -y apache2
```

### 2.2 Enable Required Modules

```bash
a2enmod rewrite
a2enmod headers
a2enmod ssl
a2enmod expires
a2enmod deflate
a2enmod proxy
a2enmod proxy_fcgi
```

### 2.3 Start and Enable Apache

```bash
systemctl enable apache2
systemctl start apache2

# Verify
systemctl status apache2
apache2 -v
```

---

## 3. Install PHP 8.3

### 3.1 Add Ondrej PHP PPA

```bash
add-apt-repository ppa:ondrej/php -y
apt update
```

### 3.2 Install PHP 8.3 and Required Extensions

```bash
apt install -y \
    php8.3 \
    php8.3-cli \
    php8.3-common \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-pdo \
    php8.3-snmp \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-gd \
    php8.3-opcache \
    php8.3-json \
    libapache2-mod-php8.3
```

### 3.3 Verify PHP Installation

```bash
php8.3 --version
php8.3 -m | grep -E 'pdo|snmp|mbstring|curl|gd|opcache'
```

Expected output includes: `pdo_mysql`, `snmp`, `mbstring`, `curl`, `gd`, `Zend OPcache`

---

## 4. Install MariaDB 11

### 4.1 Add MariaDB Official Repository

```bash
curl -LsS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup | \
    bash -s -- --mariadb-server-version="mariadb-11.4"
apt update
```

### 4.2 Install MariaDB Server

```bash
apt install -y mariadb-server mariadb-client
```

### 4.3 Start and Enable MariaDB

```bash
systemctl enable mariadb
systemctl start mariadb

# Verify
systemctl status mariadb
mariadb --version
```

### 4.4 Secure MariaDB Installation

```bash
mariadb-secure-installation
```

Answer the prompts:
```
Enter current password for root (press enter if none): [ENTER]
Switch to unix_socket authentication? [Y/n]: n
Change the root password? [Y/n]: Y
New password: [strong-root-password]
Re-enter new password: [strong-root-password]
Remove anonymous users? [Y/n]: Y
Disallow root login remotely? [Y/n]: Y
Remove test database and access to it? [Y/n]: Y
Reload privilege tables now? [Y/n]: Y
```

---

## 5. Install MRTG & SNMP Tools

### 5.1 Install Net-SNMP Tools

```bash
apt install -y \
    snmp \
    snmpd \
    snmp-mibs-downloader \
    libsnmp-dev
```

### 5.2 Enable SNMP MIB Loading

```bash
# Uncomment the mibs line to load all MIBs
sed -i 's/^mibs :/# mibs :/' /etc/snmp/snmp.conf

# Or set explicitly
cat > /etc/snmp/snmp.conf << 'EOF'
mibs +ALL
mib-dirs /usr/share/snmp/mibs:/var/www/noc/snmp/mibs
EOF
```

### 5.3 Download SNMP MIBs

```bash
download-mibs
```

### 5.4 Install MRTG

```bash
apt install -y mrtg
```

### 5.5 Verify MRTG Installation

```bash
mrtg --version
which cfgmaker
which indexmaker
```

### 5.6 Create MRTG Directories

```bash
# MRTG config directory
mkdir -p /etc/mrtg
chmod 750 /etc/mrtg
chown www-data:www-data /etc/mrtg

# MRTG output / graphs directory
mkdir -p /var/www/mrtg/graphs
mkdir -p /var/www/mrtg/data
chmod -R 755 /var/www/mrtg
chown -R www-data:www-data /var/www/mrtg
```

---

## 6. Configure PHP

### 6.1 Create PHP Production Configuration

```bash
cp /etc/php/8.3/apache2/php.ini /etc/php/8.3/apache2/php.ini.bak
```

```bash
# Apply production settings
cat > /etc/php/8.3/apache2/conf.d/99-noc.ini << 'EOF'
; NOC MRTG Manager PHP Configuration
; PHP 8.3 Production Settings

[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,parse_ini_file,show_source
disable_classes =
expose_php = Off

; Error handling (production)
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/noc/php_error.log
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On
html_errors = Off

; Resource limits
max_execution_time = 60
max_input_time = 60
memory_limit = 256M
max_input_vars = 3000

; File uploads
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 5
post_max_size = 12M

; Session
session.save_handler = files
session.save_path = /var/lib/php/sessions
session.use_strict_mode = 1
session.cookie_httponly = 1
session.cookie_secure = 0
session.cookie_samesite = Strict
session.gc_maxlifetime = 1800
session.name = noc_session
session.use_only_cookies = 1

; Date / Timezone
date.timezone = Asia/Jakarta

; SNMP extension
extension = snmp

; Security
; Note: exec/shell_exec are disabled for web requests via Apache.
; The CLI php.ini (conf.d/99-noc.ini) re-enables them for cron scripts
; that must invoke /usr/bin/mrtg and snmpwalk. All shell arguments passed
; to these functions in cron scripts are validated and escaped with
; escapeshellarg() before use.
open_basedir = /var/www/noc:/var/www/mrtg:/var/log/noc:/etc/mrtg:/tmp
allow_url_fopen = Off
allow_url_include = Off

[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 0
EOF
```

### 6.2 Apply Same Settings for CLI

```bash
cp /etc/php/8.3/apache2/conf.d/99-noc.ini /etc/php/8.3/cli/conf.d/99-noc.ini

# For CLI: allow exec (needed for cron scripts that call mrtg/snmpwalk)
sed -i 's/^disable_functions = exec.*/disable_functions =/' \
    /etc/php/8.3/cli/conf.d/99-noc.ini

# For CLI: relax open_basedir
sed -i 's|^open_basedir = .*|open_basedir = /var/www/noc:/var/www/mrtg:/var/log/noc:/etc/mrtg:/usr/bin:/tmp|' \
    /etc/php/8.3/cli/conf.d/99-noc.ini
```

### 6.3 Create Session Directory

```bash
mkdir -p /var/lib/php/sessions
chown www-data:www-data /var/lib/php/sessions
chmod 700 /var/lib/php/sessions
```

---

## 7. Configure Apache VirtualHost

### 7.1 Disable Default Site

```bash
a2dissite 000-default.conf
```

### 7.2 Create NOC VirtualHost Configuration

```bash
cat > /etc/apache2/sites-available/noc-manager.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    ServerAlias noc.triraintiutama.local

    DocumentRoot /var/www/noc/public

    # PHP handler
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    <Directory /var/www/noc/public>
        Options -Indexes -FollowSymLinks +SymLinksIfOwnerMatch
        AllowOverride All
        Require all granted

        # Security headers
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    </Directory>

    # MRTG graphs served directly
    Alias /mrtg /var/www/mrtg
    <Directory /var/www/mrtg>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    # Block access to sensitive directories
    <DirectoryMatch "^/var/www/noc/(core|config|database|cron|snmp)">
        Require all denied
    </DirectoryMatch>

    # PHP error log
    php_value error_log /var/log/noc/php_error.log

    # Access and error logs
    ErrorLog /var/log/apache2/noc-error.log
    CustomLog /var/log/apache2/noc-access.log combined

    # Compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml
        AddOutputFilterByType DEFLATE text/css application/javascript
        AddOutputFilterByType DEFLATE application/json
    </IfModule>

    # Browser caching for static assets
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/png "access plus 7 days"
        ExpiresByType image/gif "access plus 7 days"
        ExpiresByType text/css "access plus 1 day"
        ExpiresByType application/javascript "access plus 1 day"
    </IfModule>
</VirtualHost>
EOF
```

### 7.3 Enable the Site

```bash
a2ensite noc-manager.conf
apache2ctl configtest
systemctl reload apache2
```

### 7.4 Create Public .htaccess

```bash
cat > /var/www/noc/public/.htaccess << 'EOF'
Options -Indexes
RewriteEngine On

# Redirect to HTTPS (uncomment when SSL is configured)
# RewriteCond %{HTTPS} off
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route all requests through index.php (front controller)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]

# Block access to hidden files
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Block direct PHP execution outside public/
<FilesMatch "\.php$">
    Require all granted
</FilesMatch>
EOF
```

---

## 8. Configure MariaDB

### 8.1 Create MariaDB Production Config

```bash
cat > /etc/mysql/conf.d/noc-manager.cnf << 'EOF'
[mysqld]
# Network
bind-address            = 127.0.0.1
port                    = 3306

# General
default-storage-engine  = InnoDB
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
skip-name-resolve
skip-external-locking

# InnoDB settings
innodb_buffer_pool_size         = 256M
innodb_buffer_pool_instances    = 1
innodb_log_file_size            = 64M
innodb_log_buffer_size          = 8M
innodb_flush_log_at_trx_commit  = 1
innodb_flush_method             = O_DIRECT
innodb_file_per_table           = 1

# Query cache
query_cache_type        = 0
query_cache_size        = 0

# Connection limits
max_connections         = 100
max_connect_errors      = 100
wait_timeout            = 600
interactive_timeout     = 600

# Logging
slow_query_log          = 1
slow_query_log_file     = /var/log/mysql/mariadb-slow.log
long_query_time         = 2

# Security
local-infile            = 0
symbolic-links          = 0

[client]
default-character-set   = utf8mb4
port                    = 3306
socket                  = /run/mysqld/mysqld.sock
EOF
```

### 8.2 Restart MariaDB

```bash
systemctl restart mariadb
```

### 8.3 Create Database and User

```bash
mariadb -u root -p << 'EOF'
-- Create database
CREATE DATABASE IF NOT EXISTS noc_manager
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create application user (local only)
CREATE USER IF NOT EXISTS 'noc_user'@'127.0.0.1'
    IDENTIFIED BY 'Pusing7Keliling';

-- Grant only necessary privileges
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES
    ON noc_manager.* TO 'noc_user'@'127.0.0.1';

FLUSH PRIVILEGES;

-- Verify
SELECT User, Host FROM mysql.user WHERE User = 'noc_user';
EOF
```

### 8.4 Import Database Schema

```bash
mariadb -u root -p noc_manager < /var/www/noc/database/schema.sql
```

### 8.5 Import Seed Data

```bash
mariadb -u root -p noc_manager < /var/www/noc/database/seed.sql
```

### 8.6 Verify Schema

```bash
mariadb -u noc_user -p'Pusing7Keliling' -h 127.0.0.1 noc_manager -e "SHOW TABLES;"
```

---

## 9. Deploy Application Files

### 9.1 Create Application Directory Structure

```bash
mkdir -p /var/www/noc/{public,api/v1,core,modules,views,cron,config,database,logs,snmp/mibs,snmp/templates,mrtg,docs}
mkdir -p /var/www/noc/public/assets/{css,js,img}
mkdir -p /var/www/noc/modules/{dashboard,routers,interfaces,queues,pppoe,mrtg,reports,settings,users}

# Create views subdirectories for each module
for module in dashboard routers interfaces queues pppoe mrtg reports settings users; do
    mkdir -p /var/www/noc/modules/${module}/views
done

mkdir -p /var/www/noc/views/{layouts,partials}
```

### 9.2 Copy Application Files

```bash
# If deploying from a repository:
# git clone https://github.com/yourorg/noc-mrtg.git /var/www/noc

# If deploying from an archive:
# tar -xzf noc-manager.tar.gz -C /var/www/noc

# Ensure proper ownership after copy
chown -R www-data:www-data /var/www/noc
```

### 9.3 Create Log Directory

```bash
mkdir -p /var/log/noc
chown -R www-data:www-data /var/log/noc
chmod 750 /var/log/noc

# Create initial log files
touch /var/log/noc/app.log \
      /var/log/noc/error.log \
      /var/log/noc/snmp.log \
      /var/log/noc/audit.log \
      /var/log/noc/php_error.log

chown www-data:www-data /var/log/noc/*.log
chmod 640 /var/log/noc/*.log
```

### 9.4 Create Symlinks

```bash
# Symlink logs directory into application
ln -sfn /var/log/noc /var/www/noc/logs

# Symlink MRTG config into application
ln -sfn /etc/mrtg /var/www/noc/mrtg

# Symlink MRTG output into public for web access
ln -sfn /var/www/mrtg /var/www/noc/public/mrtg
```

---

## 10. Configure Application Environment

### 10.1 Create Environment File

```bash
cat > /var/www/noc/config/.env << 'EOF'
# Application
APP_NAME="NOC MRTG Manager"
APP_VERSION=1.0.0
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_SECRET=Bismillah411BerkahTriraIntiUtama
APP_DIR=/var/www/noc
APP_TIMEZONE=Asia/Jakarta

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=noc_manager
DB_USER=noc_user
DB_PASS=Pusing7Keliling
DB_CHARSET=utf8mb4

# MRTG
MRTG_DIR=/var/www/mrtg
MRTG_CFG=/etc/mrtg
MRTG_BIN=/usr/bin/mrtg
CFGMAKER_BIN=/usr/bin/cfgmaker

# SNMP
SNMP_TIMEOUT=3000000
SNMP_RETRIES=2
SNMP_VERSION=2c

# Logging
LOG_DIR=/var/log/noc
LOG_LEVEL=info

# Security
SESSION_LIFETIME=1800
SESSION_NAME=noc_session
CSRF_TOKEN_EXPIRY=3600
MAX_LOGIN_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
EOF
```

### 10.2 Secure Environment File

```bash
chown root:www-data /var/www/noc/config/.env
chmod 640 /var/www/noc/config/.env
```

---

## 11. Initialize Database

### 11.1 Run Schema and Seed

```bash
# Import schema
mariadb -u root -p noc_manager < /var/www/noc/database/schema.sql

# Import seed data
mariadb -u root -p noc_manager < /var/www/noc/database/seed.sql
```

### 11.2 Verify Default Admin User

```bash
mariadb -u root -p noc_manager -e \
    "SELECT id, username, full_name, role, status FROM users;"
```

Expected output:
```
+----+----------+---------------+-------+--------+
| id | username | full_name     | role  | status |
+----+----------+---------------+-------+--------+
|  1 | admin    | Administrator | admin | active |
+----+----------+---------------+-------+--------+
```

Default login: **admin** / **Admin@123**  
**Change this password immediately after first login.**

---

## 12. Configure MRTG

### 12.1 Create Global MRTG Configuration

```bash
cat > /etc/mrtg/mrtg.cfg << 'EOF'
# MRTG Global Configuration
# NOC MRTG Manager
# Generated: 2025-01-01

# Global settings
WorkDir: /var/www/mrtg
WriteExpires: Yes
Options[_]: growright,bits,nopercent
Refresh: 300
Interval: 5
Language: english
LogFormat: rrdtool

# Web interface settings
HtmlDir: /var/www/mrtg
ImageDir: /var/www/mrtg/graphs
LogDir: /var/www/mrtg/data

# Include per-router configs
Include: /etc/mrtg/router_*.cfg
EOF
```

### 12.2 Set MRTG Directory Permissions

```bash
chown -R www-data:www-data /etc/mrtg
chmod 750 /etc/mrtg
chmod 640 /etc/mrtg/*.cfg
```

### 12.3 Test MRTG with a Sample Config

After adding a router through the web UI, test by running:

```bash
# Run as www-data to ensure permissions are correct
sudo -u www-data /usr/bin/mrtg /etc/mrtg/router_1.cfg --logging /var/log/noc/mrtg.log
```

---

## 13. Set Up Cron Jobs

### 13.1 Create System Crontab Entry

```bash
cat > /etc/cron.d/noc-manager << 'EOF'
# NOC MRTG Manager – Scheduled Tasks
# All times are in Asia/Jakarta (UTC+7)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""

# MRTG polling – every 5 minutes
*/5 * * * *  www-data  /usr/bin/mrtg /etc/mrtg/mrtg.cfg --logging /var/log/noc/mrtg.log 2>&1

# SNMP polling – every 5 minutes (store to database)
*/5 * * * *  www-data  /usr/bin/php8.3 /var/www/noc/cron/poll_snmp.php >> /var/log/noc/snmp.log 2>&1

# Hourly aggregation (runs at minute 1 of each hour)
1 * * * *    www-data  /usr/bin/php8.3 /var/www/noc/cron/aggregate_hourly.php >> /var/log/noc/app.log 2>&1

# Daily aggregation (runs at 00:05 daily)
5 0 * * *    www-data  /usr/bin/php8.3 /var/www/noc/cron/aggregate_daily.php >> /var/log/noc/app.log 2>&1

# Weekly aggregation (runs Monday 00:10)
10 0 * * 1   www-data  /usr/bin/php8.3 /var/www/noc/cron/aggregate_weekly.php >> /var/log/noc/app.log 2>&1

# Cleanup old raw traffic data (runs at 02:00 daily)
0 2 * * *    www-data  /usr/bin/php8.3 /var/www/noc/cron/cleanup.php >> /var/log/noc/app.log 2>&1
EOF
```

### 13.2 Set Crontab Permissions

```bash
chmod 644 /etc/cron.d/noc-manager
chown root:root /etc/cron.d/noc-manager
```

### 13.3 Verify Cron is Running

```bash
systemctl status cron
tail -f /var/log/syslog | grep CRON
```

---

## 14. Configure UFW Firewall

### 14.1 Install and Enable UFW

```bash
apt install -y ufw
ufw --force reset
```

### 14.2 Default Policies

```bash
# Default: deny incoming, allow outgoing
ufw default deny incoming
ufw default allow outgoing
```

### 14.3 Allow Essential Services

```bash
# SSH (restrict to management network if possible)
ufw allow 22/tcp comment 'SSH'

# HTTP
ufw allow 80/tcp comment 'HTTP'

# HTTPS (for future SSL)
ufw allow 443/tcp comment 'HTTPS'
```

### 14.4 Allow SNMP Outbound (already covered by default allow outgoing)

```bash
# SNMP responses come back to our server (stateful – handled automatically)
# No additional rule needed for outbound UDP 161 polling
```

### 14.5 Optional: Restrict SSH to Management Subnet

```bash
# If SSH should only be from management network:
# ufw delete allow 22/tcp
# ufw allow from 10.123.123.0/24 to any port 22 comment 'SSH from management'
```

### 14.6 Enable UFW

```bash
ufw --force enable
ufw status verbose
```

### 14.7 Verify Rules

```bash
ufw status numbered
```

Expected output:
```
     To                         Action      From
     --                         ------      ----
[ 1] 22/tcp                     ALLOW IN    Anywhere
[ 2] 80/tcp                     ALLOW IN    Anywhere
[ 3] 443/tcp                    ALLOW IN    Anywhere
```

---

## 15. Set File Permissions

### 15.1 Application Ownership

```bash
# Set www-data as owner of entire application
chown -R www-data:www-data /var/www/noc
chown -R www-data:www-data /var/www/mrtg
chown -R www-data:www-data /var/log/noc
chown -R www-data:www-data /etc/mrtg
```

### 15.2 Directory Permissions

```bash
# Web-accessible public directory
find /var/www/noc/public -type d -exec chmod 755 {} \;
find /var/www/noc/public -type f -exec chmod 644 {} \;

# Application directories (not web-accessible)
find /var/www/noc/core -type d -exec chmod 750 {} \;
find /var/www/noc/core -type f -exec chmod 640 {} \;

find /var/www/noc/modules -type d -exec chmod 750 {} \;
find /var/www/noc/modules -type f -exec chmod 640 {} \;

find /var/www/noc/api -type d -exec chmod 750 {} \;
find /var/www/noc/api -type f -exec chmod 640 {} \;

find /var/www/noc/cron -type d -exec chmod 750 {} \;
find /var/www/noc/cron -type f -exec chmod 750 {} \;

# Config – highly sensitive
chmod 750 /var/www/noc/config
chmod 640 /var/www/noc/config/.env
chown root:www-data /var/www/noc/config/.env

# MRTG directories
find /var/www/mrtg -type d -exec chmod 755 {} \;
find /var/www/mrtg -type f -exec chmod 644 {} \;

# MRTG config directory
chmod 750 /etc/mrtg
find /etc/mrtg -name '*.cfg' -exec chmod 640 {} \;

# Log directory
chmod 750 /var/log/noc
chmod 640 /var/log/noc/*.log

# Database scripts (read-only after import)
chmod 440 /var/www/noc/database/*.sql
```

### 15.3 Verify Critical Permissions

```bash
ls -la /var/www/noc/config/
ls -la /var/www/noc/public/
ls -la /etc/mrtg/
```

---

## 16. Verify Installation

### 16.1 Check All Services

```bash
systemctl status apache2 mariadb cron
```

### 16.2 Check PHP Extensions

```bash
php8.3 -m | sort
```

Must include: `pdo_mysql`, `snmp`, `mbstring`, `curl`, `gd`, `opcache`, `json`, `xml`

### 16.3 Test Database Connection

```bash
mariadb -u noc_user -p'Pusing7Keliling' -h 127.0.0.1 noc_manager \
    -e "SELECT COUNT(*) AS tables FROM information_schema.tables WHERE table_schema='noc_manager';"
```

Expected: 13 tables

### 16.4 Test SNMP Connectivity

```bash
# Test against a known MikroTik router (replace with actual router IP)
snmpwalk -v2c -c public 192.168.1.1 .1.3.6.1.2.1.1.1.0
```

### 16.5 Test Web Application

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost/
```

Expected: `200`

```bash
curl -I http://localhost/
```

Expected: `HTTP/1.1 200 OK` with `X-Content-Type-Options: nosniff` header.

### 16.6 Test PHP SNMP from CLI

```bash
php8.3 -r "echo snmpget('127.0.0.1', 'public', '.1.3.6.1.2.1.1.1.0');"
```

### 16.7 Check Log Files

```bash
tail -20 /var/log/apache2/noc-error.log
tail -20 /var/log/noc/php_error.log
```

### 16.8 Post-Installation Checklist

- [ ] Apache is running and serving on port 80
- [ ] PHP 8.3 is active with all required extensions
- [ ] MariaDB is running; `noc_manager` database exists with 13 tables
- [ ] Admin user `admin` / `Admin@123` can log in
- [ ] MRTG directories `/etc/mrtg` and `/var/www/mrtg` are writable by `www-data`
- [ ] Cron jobs are active in `/etc/cron.d/noc-manager`
- [ ] UFW is enabled with ports 22, 80, 443 open
- [ ] Log files are created and writable
- [ ] `/var/www/noc/config/.env` is not web-accessible

---

## Appendix A: Troubleshooting Common Installation Issues

### Apache fails to start

```bash
apache2ctl configtest
journalctl -xe | grep apache2
```

Common fix: Syntax error in VirtualHost config. Check `/etc/apache2/sites-available/noc-manager.conf`.

### PHP SNMP extension not loading

```bash
php8.3 --ini | grep 'Loaded Configuration'
php8.3 -m | grep snmp
```

If not found:
```bash
apt install --reinstall php8.3-snmp
phpenmod snmp
systemctl restart apache2
```

### MariaDB connection refused

```bash
systemctl status mariadb
ss -tlnp | grep 3306
```

If not listening on 127.0.0.1:3306:
```bash
grep bind-address /etc/mysql/conf.d/noc-manager.cnf
systemctl restart mariadb
```

### MRTG permission denied writing graphs

```bash
ls -la /var/www/mrtg/
chown -R www-data:www-data /var/www/mrtg /etc/mrtg
```

### Cron jobs not running

```bash
systemctl status cron
cat /etc/cron.d/noc-manager
# Verify no trailing space after username field
```

---

## Appendix B: MikroTik SNMP Configuration

On the MikroTik router, enable SNMP v2c:

```routeros
/snmp set enabled=yes
/snmp community
add name=public addresses=10.123.123.202/32 read-access=yes write-access=no
```

Verify from the NOC server:
```bash
snmpwalk -v2c -c public <router-ip> .1.3.6.1.2.1.1
```
