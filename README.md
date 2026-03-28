# NOC MRTG Manager

A production-ready **Network Operations Center (NOC)** tool for managing MikroTik routers and MRTG (Multi Router Traffic Grapher) integration.

## Features

- **Router Management** — Discover, monitor, and manage MikroTik routers via SNMP v2c
- **Traffic Monitoring** — Poll interfaces, simple queues, and PPPoE sessions in real-time
- **MRTG Integration** — Auto-generate MRTG configuration files for long-term trend analysis
- **Web Dashboard** — Real-time bandwidth monitoring, historical charts, and SNMP discovery
- **Authentication** — Session-based login with bcrypt password hashing and CSRF protection
- **RESTful API** — `/api/v1/` endpoints for programmatic access

## Requirements

- PHP 8.3+ with `snmp` extension
- MariaDB 11.x / MySQL 8.x
- Apache 2.4 with `mod_rewrite`
- MRTG and SNMP tools (`mrtg`, `snmpwalk`, `snmpget`)

## Quick Start

```bash
# 1. Copy and configure the environment file
cp .env.example .env
vi .env   # set DB_*, APP_SECRET, APP_URL, etc.

# 2. Create the database and load schema + seed data
mariadb -u root -p -e "CREATE DATABASE noc_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -u root -p noc_manager < database/schema.sql
mariadb -u root -p noc_manager < database/seed.sql

# 3. Set file permissions
chown -R www-data:www-data /var/www/noc
chmod -R 755 /var/www/noc
chmod -R 775 /var/www/noc/storage /var/log/noc

# 4. Configure Apache VirtualHost (see docs/INSTALL.md)

# 5. Schedule cron jobs
# */5 * * * * php <INSTALL_PATH>/cron/poller.php
# 0 * * * *   php <INSTALL_PATH>/cron/discovery.php
# */15 * * * * php <INSTALL_PATH>/cron/mrtg_generate.php
# 5 * * * *   php <INSTALL_PATH>/cron/aggregate.php
# 0 0 * * *   php <INSTALL_PATH>/cron/cleanup.php
# Replace <INSTALL_PATH> with the directory where the application is deployed.
```

Default admin credentials (change immediately after first login):
- **Username:** `admin`
- **Password:** `Admin@123`

## Documentation

- [`docs/INSTALL.md`](docs/INSTALL.md) — Full installation guide
- [`docs/DEPLOY.md`](docs/DEPLOY.md) — Deployment and production hardening
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — System architecture overview
- [`docs/BLUEPRINT.md`](docs/BLUEPRINT.md) — Feature blueprint and roadmap

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.3+ (strict types) |
| Database | MariaDB 11.x / MySQL 8.x |
| Web Server | Apache 2.4 |
| Network | SNMP v2c via `snmp2_*` PHP functions |
| Graphing | MRTG |

## Project Structure

```
├── api/          REST API entry points and v1 resource endpoints
├── config/       Bootstrap, database, and SNMP configuration
├── core/         Framework: Router, Database, Auth, Session, Logger, Response
├── cron/         Background jobs: poller, discovery, MRTG generation, aggregation, cleanup
├── database/     SQL schema and seed data
├── docs/         Installation, deployment, and architecture documentation
├── modules/      Domain modules: auth, dashboard, routers, interfaces, queues, pppoe, monitoring, mrtg, settings
├── mrtg/         MRTG config generator and data parser
├── public/       Web root: index.php, assets (CSS/JS), and views
└── snmp/         SNMP manager, poller, and discovery classes
```

## License

Proprietary — All rights reserved.
