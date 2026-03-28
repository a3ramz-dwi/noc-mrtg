# MRTG Manager – System Architecture

**Project:** NOC MRTG Manager  
**Version:** 1.0.0  
**Environment:** Ubuntu 24.04 LTS  
**Last Updated:** 2025-01-01

---

## 1. ASCII Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          NOC MRTG Manager – Production Stack                    │
│                         Ubuntu 24.04 LTS · IP: 10.123.123.202                   │
└─────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────┐          ┌──────────────────────────────────────┐
  │      BROWSER / CLIENT    │          │          MikroTik Routers            │
  │   NOC Engineer / Admin   │          │   RouterOS v6/v7  ·  SNMP v2c        │
  │   http://noc.trira...    │          │   community: public / custom         │
  └──────────────┬───────────┘          └──────────────┬───────────────────────┘
                 │ HTTP/HTTPS                          │ UDP/161 SNMP
                 ▼                                     ▼
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │                          Apache 2.4 Web Server                               │
  │   ServerName: localhost   ServerAlias: noc.triraintiutama.local              │
  │   DocumentRoot: /var/www/noc/public                                          │
  │   VirtualHost :80 (HTTP)  │  VirtualHost :443 (HTTPS/optional)               │
  └────────────────────────────────────┬─────────────────────────────────────────┘
                                       │ mod_php / php-fpm
                                       ▼
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │                          PHP 8.3 Application Layer                           │
  │   /var/www/noc/                                                              │
  │   ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐                │
  │   │   public/  │ │    api/    │ │   core/    │ │  modules/  │                │
  │   │  index.php │ │ endpoints  │ │ bootstrap  │ │ dashboard  │                │
  │   │  assets/   │ │  REST API  │ │ router     │ │ routers    │                │
  │   └────────────┘ └────────────┘ │ db(PDO)    │ │ interfaces │                │
  │                                 │ auth/sess  │ │ queues     │                │
  │                                 │ helpers    │ │ pppoe      │                │
  │                                 └────────────┘ │ mrtg       │                │
  │                                                │ reports    │                │
  │                                                │ settings   │                │
  │                                                └────────────┘                │
  └───────────┬──────────────────────────────────┬───────────────────────────────┘
              │ PDO/MySQL                        │ exec() / shell_exec()
              ▼                                  ▼
  ┌───────────────────────────┐     ┌─────────────────────────────────────────┐
  │   MariaDB 11 Database     │     │          MRTG + SNMP Tools              │
  │   DB: noc_manager         │     │                                         │
  │   User: noc_user          │     │  ┌───────────┐   ┌──────────────────┐   │
  │                           │     │  │  cfgmaker │   │   mrtg daemon    │   │
  │   Tables:                 │     │  │ (config   │   │  /usr/bin/mrtg   │   │
  │   - routers               │     │  │  generator│   │  polls every 5m  │   │
  │   - interfaces            │     │  └───────────┘   └──────────────────┘   │
  │   - simple_queues         │     │                                         │
  │   - pppoe_users           │     │  ┌───────────┐   ┌──────────────────┐   │
  │   - traffic_data          │     │  │  snmpwalk │   │   mrtg configs   │   │
  │   - traffic_daily         │     │  │  snmpget  │   │  /etc/mrtg/*.cfg │   │
  │   - traffic_weekly        │     │  └───────────┘   └──────────────────┘   │
  │   - traffic_monthly       │     │                                         │
  │   - mrtg_configs          │     │  ┌───────────┐   ┌──────────────────┐   │
  │   - users                 │     │  │ mrtg data │   │  mrtg HTML/PNG   │   │
  │   - settings              │     │  │  .log     │   │  /var/www/mrtg/  │   │
  │   - audit_log             │     │  │  files    │   │  graphs/*.png    │   │
  │   - login_attempts        │     │  └───────────┘   └──────────────────┘   │
  └───────────────────────────┘     └─────────────────────────────────────────┘
              │
              │ (read aggregated data for charts)
              ▼
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │                      Cron Jobs (/etc/cron.d/noc-manager)                     │
  │                                                                              │
  │  */5 * * * *   mrtg /etc/mrtg/*.cfg         → Poll & generate graphs         │
  │  */5 * * * *   php /var/www/noc/cron/poll_snmp.php   → Store to DB           │
  │  0 * * * *     php /var/www/noc/cron/aggregate_hourly.php → Hourly agg       │
  │  0 0 * * *     php /var/www/noc/cron/aggregate_daily.php  → Daily agg        │
  │  0 0 * * 0     php /var/www/noc/cron/aggregate_weekly.php → Weekly agg       │
  │  0 2 * * *     php /var/www/noc/cron/cleanup.php          → Purge old data   │
  └──────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────────────────────────────────────────────────────┐
  │                             Log Files                                        │
  │   /var/log/noc/app.log          Application events                           │
  │   /var/log/noc/error.log        PHP/application errors                       │
  │   /var/log/noc/snmp.log         SNMP polling results                         │
  │   /var/log/noc/audit.log        Security audit trail                         │
  │   /var/log/apache2/noc-*.log    Apache access/error logs                     │
  └──────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Application Components                       │
└─────────────────────────────────────────────────────────────────────┘

  ┌───────────────────────────────────────────────────────────────────┐
  │  PRESENTATION LAYER                                               │
  │                                                                   │
  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐       │
  │  │   Dashboard    │  │  Router Mgmt   │  │   Interface    │       │
  │  │   - Live stats │  │  - Add/Edit    │  │   Monitor      │       │
  │  │   - Summary    │  │  - SNMP test   │  │   - Traffic    │       │
  │  │   - Alerts     │  │  - Status      │  │   - Graphs     │       │
  │  └────────────────┘  └────────────────┘  └────────────────┘       │
  │                                                                   │
  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐       │
  │  │  Queue Monitor │  │  PPPoE Monitor │  │  MRTG Viewer   │       │
  │  │  - Bandwidth   │  │  - Sessions    │  │  - Embedded    │       │
  │  │  - Usage       │  │  - Usage stats │  │    graphs      │       │
  │  └────────────────┘  └────────────────┘  └────────────────┘       │
  │                                                                   │
  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐       │
  │  │    Reports     │  │   Settings     │  │  User Manager  │       │
  │  │  - PDF/CSV     │  │  - App config  │  │  - RBAC        │       │
  │  │  - Charts      │  │  - SNMP cfg    │  │  - Audit log   │       │
  │  └────────────────┘  └────────────────┘  └────────────────┘       │
  │                                                                   │
  │  Technology: HTML5, Bootstrap 5, Chart.js, DataTables, AJAX       │
  └───────────────────────────────────────────────────────────────────┘
              │ HTTP Requests                │ JSON Responses
              ▼                              ▼
  ┌──────────────────────────────────────────────────────────────────┐
  │  APPLICATION LAYER (PHP 8.3)                                     │
  │                                                                  │
  │  ┌───────────────────────────────────────────────────────────┐   │
  │  │  core/                                                    │   │
  │  │  ├── App.php          (Bootstrap, DI container)           │   │
  │  │  ├── Router.php       (URL routing)                       │   │
  │  │  ├── Database.php     (PDO singleton, query builder)      │   │
  │  │  ├── Auth.php         (Session-based authentication)      │   │
  │  │  ├── Session.php      (Session management + CSRF)         │   │
  │  │  ├── Config.php       (Environment config loader)         │   │
  │  │  ├── Logger.php       (PSR-3 compatible file logger)      │   │
  │  │  ├── Snmp.php         (SNMP wrapper using PHP snmp ext)   │   │
  │  │  ├── MrtgManager.php  (MRTG config generation/mgmt)       │   │
  │  │  ├── Response.php     (HTTP response helpers)             │   │
  │  │  └── Validator.php    (Input validation/sanitization)     │   │
  │  └───────────────────────────────────────────────────────────┘   │
  │                                                                  │
  │  ┌───────────────────────────────────────────────────────────┐   │
  │  │  modules/                                                 │   │
  │  │  ├── dashboard/    ├── routers/    ├── interfaces/        │   │
  │  │  ├── queues/       ├── pppoe/      ├── mrtg/              │   │
  │  │  ├── reports/      ├── settings/   └── users/             │   │
  │  └───────────────────────────────────────────────────────────┘   │
  │                                                                  │
  │  ┌───────────────────────────────────────────────────────────┐   │
  │  │  api/                                                     │   │
  │  │  ├── v1/routers.php      ├── v1/interfaces.php            │   │
  │  │  ├── v1/queues.php       ├── v1/pppoe.php                 │   │
  │  │  ├── v1/traffic.php      ├── v1/mrtg.php                  │   │
  │  │  └── v1/snmp.php                                          │   │
  │  └───────────────────────────────────────────────────────────┘   │
  └──────────────────────────────────────────────────────────────────┘
              │ PDO Queries                  │ SNMP/Shell calls
              ▼                              ▼
  ┌───────────────────────┐    ┌─────────────────────────────────┐
  │  DATA LAYER           │    │  EXTERNAL SERVICES              │
  │                       │    │                                 │
  │  MariaDB 11           │    │  SNMP v2c → MikroTik Routers    │
  │  Engine: InnoDB       │    │  MRTG → /etc/mrtg/*.cfg         │
  │  Charset: utf8mb4     │    │  cfgmaker → config generation   │
  │  Collation:           │    │  snmpwalk/snmpget → discovery   │
  │  utf8mb4_unicode_ci   │    │                                 │
  └───────────────────────┘    └─────────────────────────────────┘
```

---

## 3. Data Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│                         DATA FLOW DIAGRAM                            │
└──────────────────────────────────────────────────────────────────────┘

  ╔══════════════╗
  ║  NOC Admin   ║
  ╚══════╤═══════╝
         │
         │ 1. Add Router (IP, SNMP community)
         ▼
  ┌─────────────────┐      2. Validate & save         ┌──────────────────┐
  │  PHP Web App    │ ────────────────────────────▶  │  MariaDB         │
  │  /modules/      │ ◀────────────────────────────  │  routers table   │
  │  routers/add    │      3. Return router_id        └──────────────────┘
  └────────┬────────┘
           │
           │ 4. Trigger SNMP Discovery (or cron)
           ▼
  ┌─────────────────┐      5. snmpwalk OIDs          ┌──────────────────┐
  │  core/Snmp.php  │ ────────────────────────────▶ │  MikroTik Router │
  │  SNMP v2c       │ ◀──────────────────────────── │  UDP 161         │
  └────────┬────────┘      6. OID responses          └──────────────────┘
           │
           │ 7. Parse interfaces, queues, PPPoE
           ▼
  ┌─────────────────┐      8. Insert discovered      ┌──────────────────┐
  │  Discovery      │ ────────────────────────────▶ │  MariaDB         │
  │  Module         │                                │  interfaces      │
  └────────┬────────┘                                │  simple_queues   │
           │                                         │  pppoe_users     │
           │ 9. Generate MRTG config                 └──────────────────┘
           ▼
  ┌─────────────────┐      10. Write .cfg file       ┌──────────────────┐
  │  MrtgManager    │ ────────────────────────────▶ │  /etc/mrtg/      │
  │  .php           │      11. Save config meta      │  router_X.cfg    │
  └────────┬────────┘ ────────────────────────────▶ │  MariaDB         │
           │                                         │  mrtg_configs    │
           │                                         └──────────────────┘

  ─ ─ ─ ─ ─ ─ ─ ─ ─ CRON EVERY 5 MINUTES ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─

  ┌─────────────────┐      12. mrtg polls SNMP        ┌──────────────────┐
  │  MRTG Daemon    │ ────────────────────────────▶  │  MikroTik Router │
  │  /usr/bin/mrtg  │ ◀────────────────────────────  │  UDP 161         │
  └────────┬────────┘      13. Counter values         └──────────────────┘
           │
           │ 14. Write .log files + generate PNG graphs
           ▼
  ┌─────────────────┐      15. Serve PNG images       ┌──────────────────┐
  │  /var/www/mrtg/ │ ────────────────────────────▶  │  Browser         │
  │  graphs/*.png   │                                 │  MRTG Viewer     │
  └─────────────────┘                                 └──────────────────┘

  ┌─────────────────┐      16. Read SNMP counters     ┌──────────────────┐
  │  cron/          │ ────────────────────────────▶  │  MikroTik Router │
  │  poll_snmp.php  │ ◀────────────────────────────  └──────────────────┘
  └────────┬────────┘      17. ifInOctets/ifOutOctets
           │
           │ 18. Calculate delta bytes_in/bytes_out
           ▼
  ┌─────────────────┐      19. Insert raw traffic    ┌──────────────────┐
  │  Traffic Store  │ ──────────────────────────── ▶│  MariaDB         │
  │  Logic          │                                │  traffic_data    │
  └─────────────────┘                                └──────────────────┘
           │
  ─ ─ ─ ─ ─ ─ ─ ─ ─ CRON HOURLY/DAILY/WEEKLY ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─
           │
           │ 20. Aggregate raw → daily/weekly/monthly
           ▼
  ┌─────────────────┐                                 ┌──────────────────┐
  │  Aggregation    │ ─────────────────────────────▶  │  MariaDB         │
  │  Cron Scripts   │                                 │  traffic_daily   │
  └─────────────────┘                                 │  traffic_weekly  │
                                                      │  traffic_monthly │
                                                      └──────────────────┘
           │
           │ 21. Chart.js reads aggregated data via API
           ▼
  ┌─────────────────┐      22. GET /api/v1/traffic    ┌──────────────────┐
  │  Browser        │ ─────────────────────────────▶ │  PHP API Layer   │
  │  Chart.js       │ ◀────────────────────────────  │  JSON response   │
  └─────────────────┘      23. Render traffic charts  └──────────────────┘
```

---

## 4. Technology Stack

| Layer | Technology | Version | Role |
|-------|-----------|---------|------|
| OS | Ubuntu LTS | 24.04 | Host operating system |
| Web Server | Apache | 2.4.x | HTTP server, mod_rewrite, mod_headers |
| Language | PHP | 8.3+ | Backend application logic |
| Database | MariaDB | 11.x | Persistent storage, InnoDB engine |
| SNMP Engine | Net-SNMP | 5.9+ | SNMP v2c polling (snmpwalk/snmpget) |
| Monitoring | MRTG | 2.17+ | Traffic graphing via SNMP counters |
| Frontend CSS | Bootstrap | 5.3 | Responsive UI framework |
| Frontend Charts | Chart.js | 4.x | Interactive traffic graphs |
| Frontend Tables | DataTables | 1.13 | Sortable/filterable data tables |
| Icons | Font Awesome | 6.x | UI icons |
| PHP SNMP Ext | php8.3-snmp | - | Native PHP SNMP functions |
| PHP PDO | php8.3-mysql | - | Database abstraction (PDO_MySQL) |
| PHP Session | Built-in | - | Session-based auth with CSRF tokens |
| Cron | cron daemon | - | Scheduled SNMP polling & aggregation |
| Firewall | UFW | - | iptables frontend |
| Logging | Monolog-style | - | PSR-3 compliant file-based logging |

---

## 5. SNMP Polling Flow

```
  ┌──────────────────────────────────────────────────────────────────┐
  │                    SNMP Polling Detail Flow                      │
  └──────────────────────────────────────────────────────────────────┘

  Every 5 minutes (cron):
  php /var/www/noc/cron/poll_snmp.php

  Step 1: Load active routers from DB
  ┌─────────────────────────────────────────────────────────┐
  │  SELECT * FROM routers WHERE status = 'active'          │
  └────────────────────────┬────────────────────────────────┘
                           │ For each router:
                           ▼
  Step 2: Open SNMP session
  ┌─────────────────────────────────────────────────────────┐
  │  PHP snmp2_get / snmp2_walk                             │
  │  Host: router.ip_address                                │
  │  Community: router.snmp_community                       │
  │  Port: router.snmp_port (default: 161)                  │
  │  Timeout: 3,000,000 μs  |  Retries: 2                   │
  └────────────────────────┬────────────────────────────────┘
                           │
                           ▼
  Step 3: Poll System OIDs
  ┌─────────────────────────────────────────────────────────┐
  │  .1.3.6.1.2.1.1.1.0  → sysDescr                         │
  │  .1.3.6.1.2.1.1.3.0  → sysUpTime                        │
  │  .1.3.6.1.2.1.1.5.0  → sysName (router identity)        │
  └────────────────────────┬────────────────────────────────┘
                           │
                           ▼
  Step 4: Poll Interface OIDs (for each interface in DB)
  ┌─────────────────────────────────────────────────────────┐
  │  .1.3.6.1.2.1.2.2.1.10.{ifIndex} → ifInOctets           │
  │  .1.3.6.1.2.1.2.2.1.16.{ifIndex} → ifOutOctets          │
  │  .1.3.6.1.2.1.2.2.1.8.{ifIndex}  → ifOperStatus         │
  │  .1.3.6.1.2.1.31.1.1.1.6.{ifIndex} → ifHCInOctets       │
  │  .1.3.6.1.2.1.31.1.1.1.10.{ifIndex} → ifHCOutOctets     │
  └────────────────────────┬────────────────────────────────┘
                           │
                           ▼
  Step 5: Poll MikroTik Queue OIDs
  ┌─────────────────────────────────────────────────────────┐
  │  .1.3.6.1.4.1.14988.1.1.2.1.1.8.{idx} → queue bytes in  │
  │  .1.3.6.1.4.1.14988.1.1.2.1.1.9.{idx} → queue bytes out │
  │  .1.3.6.1.4.1.14988.1.1.2.1.1.6.{idx} → queue rate      │
  └────────────────────────┬────────────────────────────────┘
                           │
                           ▼
  Step 6: Poll PPPoE Active Sessions
  ┌─────────────────────────────────────────────────────────┐
  │  .1.3.6.1.4.1.14988.1.1.11.1.1.2.{idx} → PPPoE name     │
  │  .1.3.6.1.4.1.14988.1.1.11.1.1.3.{idx} → PPPoE service  │
  │  .1.3.6.1.4.1.14988.1.1.11.1.1.5.{idx} → PPPoE caller   │
  └────────────────────────┬────────────────────────────────┘
                           │
                           ▼
  Step 7: Calculate deltas & store
  ┌─────────────────────────────────────────────────────────┐
  │  bytes_in  = current_ifInOctets  - previous_ifInOctets  │
  │  bytes_out = current_ifOutOctets - previous_ifOutOctets │
  │  Handle 32-bit counter wraps (use HC 64-bit when avail) │
  │  INSERT INTO traffic_data (target_type, target_id,      │
  │    bytes_in, bytes_out, timestamp) VALUES (...)         │
  └─────────────────────────────────────────────────────────┘
                           │
                           ▼
  Step 8: Update router status
  ┌─────────────────────────────────────────────────────────┐
  │  UPDATE routers SET uptime = ?, status = 'active',      │
  │    updated_at = NOW() WHERE id = ?                      │
  │  Log result to /var/log/noc/snmp.log                    │
  └─────────────────────────────────────────────────────────┘
```

---

## 6. Database Design Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Entity-Relationship Overview                   │
└─────────────────────────────────────────────────────────────────────┘

  ┌─────────────┐         ┌──────────────────┐
  │   routers   │──1──∞──▶│   interfaces     │
  │  (PK: id)   │         │  (FK: router_id) │
  └──────┬──────┘         └────────┬─────────┘
         │                         │
         │         ┌───────────────┘
         │──1──∞──▶│ simple_queues │
         │         │  (FK: router_id) │
         │         └────────┬─────────┘
         │                  │
         │──1──∞──▶┌──────────────────┐
         │         │  pppoe_users     │
         │         │  (FK: router_id) │
         │         └────────┬─────────┘
         │                  │
         │──1──∞──▶┌──────────────────┐
                   │  mrtg_configs    │
                   │  (FK: router_id) │
                   └──────────────────┘

  traffic_data (polymorphic – target_type + target_id)
  ┌─────────────────────────────────────────────────────────────┐
  │  target_type = 'interface' → references interfaces.id       │
  │  target_type = 'queue'     → references simple_queues.id    │
  │  target_type = 'pppoe'     → references pppoe_users.id      │
  └─────────────────────────────────────────────────────────────┘

  Aggregation chain:
  traffic_data (raw, 5-min) → traffic_daily → traffic_weekly → traffic_monthly

  ┌─────────────┐          ┌──────────────────┐
  │    users    │──1──∞──▶│   audit_log      │
  │  (PK: id)   │          │  (FK: user_id)   │
  └─────────────┘          └──────────────────┘

  ┌─────────────┐
  │  settings   │  (key-value configuration store)
  │  (PK: id)   │
  └─────────────┘

  ┌─────────────────┐
  │  login_attempts │  (rate-limiting, brute-force protection)
  └─────────────────┘

  Key Design Decisions:
  ─────────────────────
  • All tables use InnoDB engine for ACID compliance and FK support
  • utf8mb4 charset to support full Unicode (including emoji)
  • Timestamps stored as DATETIME (UTC) not UNIX integer
  • Polymorphic FK pattern used in traffic_data for flexibility
  • Separate aggregation tables avoid GROUP BY on large raw tables
  • BCRYPT hashing (cost 12) for all passwords
  • CSRF token + session-based authentication (no JWT)
  • Soft delete pattern via 'status' fields (no hard DELETEs on routers)
  • All VARCHAR lengths chosen conservatively for index efficiency
  • Composite indexes on (target_type, target_id, timestamp) for fast chart queries
```

---

## 7. Security Architecture

```
  ┌──────────────────────────────────────────────────────────────┐
  │                    Security Layers                           │
  └──────────────────────────────────────────────────────────────┘

  Layer 1: Network
  ├── UFW firewall: allow 80, 443, 22 only
  ├── SNMP restricted to localhost / management subnet
  └── Apache mod_evasive for DoS protection

  Layer 2: Application
  ├── CSRF tokens on all state-changing forms
  ├── Session-based authentication (PHP sessions + secure cookies)
  ├── Role-Based Access Control (admin / operator / viewer)
  ├── Input validation & sanitization on all user input
  ├── Parameterized queries (PDO prepared statements) only
  ├── XSS prevention via htmlspecialchars() on all output
  └── Rate limiting on login page (login_attempts table)

  Layer 3: Data
  ├── MariaDB noc_user has ONLY SELECT/INSERT/UPDATE/DELETE on noc_manager
  ├── Passwords hashed with PASSWORD_BCRYPT (cost 12)
  ├── SNMP community strings stored encrypted at rest (AES-256)
  └── Audit log for all write operations

  Layer 4: Server
  ├── Apache runs as www-data (non-root)
  ├── PHP open_basedir restricts file access to /var/www/noc
  ├── .htaccess blocks direct access to core/, database/, config/
  └── Log files in /var/log/noc (not web-accessible)
```
