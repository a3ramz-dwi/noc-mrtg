# MRTG Manager – System Blueprint

**Project:** NOC MRTG Manager  
**Version:** 1.0.0  
**Target:** Ubuntu 24.04 LTS · PHP 8.3 · MariaDB 11 · Apache 2.4  
**Last Updated:** 2025-01-01

---

## 1. Full Feature List

### 1.1 Authentication & Security
- [x] Session-based login with CSRF protection
- [x] Bcrypt password hashing (cost 12)
- [x] Role-Based Access Control: `admin`, `operator`, `viewer`
- [x] Login brute-force protection (login_attempts table, lockout after 5 failures/15 min)
- [x] Auto session timeout (configurable, default 30 minutes)
- [x] Audit log for all write operations
- [x] Secure cookie settings (HttpOnly, SameSite=Strict)
- [x] Password change & forced password reset
- [x] Multi-user management (admin can add/edit/disable users)

### 1.2 Router Management
- [x] Add / Edit / Delete MikroTik routers
- [x] SNMP v2c connectivity test
- [x] Automatic system info discovery (identity, model, OS version, serial)
- [x] Interface discovery via SNMP walk
- [x] Simple Queue discovery via SNMP walk
- [x] PPPoE active session discovery
- [x] Router status dashboard (online/offline/warning)
- [x] Uptime tracking and display
- [x] Bulk enable/disable monitoring per router

### 1.3 Interface Monitoring
- [x] List all interfaces per router
- [x] Enable/disable monitoring per interface
- [x] Real-time traffic (bytes in/out) via SNMP
- [x] 32-bit and 64-bit counter support (ifHCInOctets/ifHCOutOctets)
- [x] Interface operational status (up/down)
- [x] Admin status vs operational status display
- [x] Speed/MTU/MAC display
- [x] Historical traffic charts (Chart.js, 1h/6h/24h/7d/30d)

### 1.4 Simple Queue Monitoring
- [x] List all simple queues per router
- [x] Max limit upload/download display
- [x] Burst limit and burst threshold display
- [x] Current queue rate via SNMP
- [x] Historical queue usage charts
- [x] Queue bytes in/out tracking

### 1.5 PPPoE User Monitoring
- [x] List active PPPoE sessions per router
- [x] Session uptime tracking
- [x] Caller ID / remote address display
- [x] Per-session traffic tracking
- [x] Session history (connects/disconnects)
- [x] Profile association display

### 1.6 MRTG Integration
- [x] Automatic MRTG config generation per router/interface
- [x] cfgmaker-based config templates for MikroTik
- [x] MRTG graph viewer embedded in web UI
- [x] Config file management (view, regenerate, delete)
- [x] MRTG status tracking (config generated, last run)
- [x] Support for custom MRTG targets (interface, queue)

### 1.7 Traffic Reports
- [x] Traffic summary per router (today, 7d, 30d)
- [x] Top interfaces by bandwidth
- [x] Top queues by usage
- [x] Top PPPoE users by traffic
- [x] CSV export for all reports
- [x] Print-friendly view
- [x] Date range selector
- [x] Aggregated charts (daily/weekly/monthly)

### 1.8 Dashboard
- [x] Total routers online/offline count
- [x] Total monitored interfaces
- [x] Total active PPPoE sessions
- [x] Total bandwidth in/out (last 5 min)
- [x] Router status summary table
- [x] Recent events / alerts feed
- [x] Quick links to top bandwidth consumers
- [x] Auto-refresh every 60 seconds

### 1.9 Settings
- [x] Application name and branding
- [x] SNMP global defaults (timeout, retries, version)
- [x] Polling interval configuration
- [x] Data retention periods (raw/daily/weekly)
- [x] Email alert configuration (SMTP)
- [x] UI theme selection (light/dark)
- [x] MRTG path configuration
- [x] Timezone setting

---

## 2. Module Descriptions

### 2.1 Module: Dashboard (`modules/dashboard/`)
**Purpose:** High-level overview of the entire NOC infrastructure.  
**Files:**
- `DashboardController.php` – Aggregates statistics from all modules
- `views/index.php` – Main dashboard template

**Key Functions:**
- `getRouterStatusSummary()` – Count of active/inactive routers
- `getTotalActiveSessions()` – PPPoE session count
- `getTopBandwidthInterfaces($limit)` – Top N interfaces by bytes/s
- `getRecentEvents($limit)` – Latest audit/alert entries

---

### 2.2 Module: Routers (`modules/routers/`)
**Purpose:** CRUD and discovery management for MikroTik routers.  
**Files:**
- `RouterController.php` – HTTP request handler
- `RouterModel.php` – Database operations
- `RouterDiscovery.php` – SNMP discovery logic
- `views/list.php`, `views/add.php`, `views/edit.php`, `views/show.php`

**Key Functions:**
- `create(array $data): int` – Add new router, returns new ID
- `update(int $id, array $data): bool` – Update router record
- `delete(int $id): bool` – Soft delete (sets status = 'inactive')
- `testSnmpConnection(int $routerId): array` – Test SNMP reachability
- `discoverInterfaces(int $routerId): int` – Walk ifTable, return count added
- `discoverQueues(int $routerId): int` – Walk MikroTik queue MIB
- `discoverPppoe(int $routerId): int` – Walk MikroTik PPPoE MIB
- `updateSystemInfo(int $routerId): void` – Update sysDescr, sysName, uptime

---

### 2.3 Module: Interfaces (`modules/interfaces/`)
**Purpose:** Monitor and display network interface traffic.  
**Files:**
- `InterfaceController.php`
- `InterfaceModel.php`
- `views/list.php`, `views/show.php`

**Key Functions:**
- `getByRouter(int $routerId): array` – Get all interfaces for a router
- `toggleMonitoring(int $id, bool $enabled): bool`
- `getTrafficHistory(int $id, string $period): array` – Returns time-series data
- `getCurrentRate(int $id): array` – Returns bytes/s in/out from latest two readings

---

### 2.4 Module: Queues (`modules/queues/`)
**Purpose:** Monitor MikroTik Simple Queue bandwidth.  
**Files:**
- `QueueController.php`
- `QueueModel.php`
- `views/list.php`, `views/show.php`

**Key Functions:**
- `getByRouter(int $routerId): array`
- `getUsagePercent(int $id): float` – Current usage vs max_limit
- `getTrafficHistory(int $id, string $period): array`

---

### 2.5 Module: PPPoE (`modules/pppoe/`)
**Purpose:** Track PPPoE session activity and traffic.  
**Files:**
- `PppoeController.php`
- `PppoeModel.php`
- `views/list.php`, `views/show.php`

**Key Functions:**
- `getActiveSessions(int $routerId): array`
- `getSessionHistory(string $name): array`
- `getTotalSessionTraffic(int $id): array`

---

### 2.6 Module: MRTG (`modules/mrtg/`)
**Purpose:** Generate and manage MRTG configuration files; embed graphs.  
**Files:**
- `MrtgController.php`
- `MrtgConfigGenerator.php`
- `views/list.php`, `views/view.php`, `views/graphs.php`

**Key Functions:**
- `generateConfig(int $routerId): string` – Generate full .cfg content
- `writeConfigFile(int $routerId): bool` – Write to /etc/mrtg/router_{id}.cfg
- `getGraphPath(int $targetId, string $type, string $period): string`
- `reloadMrtg(int $routerId): bool` – Re-run mrtg for this config

---

### 2.7 Module: Reports (`modules/reports/`)
**Purpose:** Aggregate traffic data into human-readable reports with CSV export.  
**Files:**
- `ReportController.php`
- `ReportExporter.php`
- `views/traffic.php`, `views/summary.php`

**Key Functions:**
- `generateTrafficReport(array $params): array`
- `exportCsv(array $data, string $filename): void`
- `getTopConsumers(string $period, int $limit): array`

---

### 2.8 Module: Users (`modules/users/`)
**Purpose:** User account management and RBAC.  
**Files:**
- `UserController.php`
- `UserModel.php`
- `views/list.php`, `views/add.php`, `views/edit.php`

**Key Functions:**
- `create(array $data): int` – Create user, hash password with bcrypt
- `update(int $id, array $data): bool`
- `changePassword(int $id, string $newPassword): bool`
- `disable(int $id): bool` – Set status = 'inactive'
- `getAuditLog(int $userId, int $page): array`

---

### 2.9 Module: Settings (`modules/settings/`)
**Purpose:** Key-value configuration store with UI.  
**Files:**
- `SettingsController.php`
- `SettingsModel.php`
- `views/index.php`

**Key Functions:**
- `get(string $key, mixed $default = null): mixed`
- `set(string $key, mixed $value): bool`
- `getAll(): array`
- `bulkUpdate(array $data): bool`

---

## 3. API Endpoint Documentation

All API endpoints require a valid session cookie. JSON responses use the envelope:
```json
{
  "success": true|false,
  "data": { ... },
  "message": "Human-readable message",
  "errors": []
}
```

### 3.1 Routers API (`/api/v1/routers.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/routers` | operator | List all routers |
| GET | `/api/v1/routers/{id}` | operator | Get single router detail |
| POST | `/api/v1/routers` | admin | Create new router |
| PUT | `/api/v1/routers/{id}` | admin | Update router |
| DELETE | `/api/v1/routers/{id}` | admin | Soft-delete router |
| POST | `/api/v1/routers/{id}/test` | operator | Test SNMP connection |
| POST | `/api/v1/routers/{id}/discover` | admin | Run full discovery |
| GET | `/api/v1/routers/{id}/status` | viewer | Get router status |

**POST /api/v1/routers – Request Body:**
```json
{
  "name": "Router-ISP-01",
  "ip_address": "192.168.1.1",
  "snmp_community": "public",
  "snmp_version": "2c",
  "snmp_port": 161,
  "username": "admin",
  "password": "router_password"
}
```

---

### 3.2 Interfaces API (`/api/v1/interfaces.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/interfaces?router_id={id}` | operator | List interfaces for router |
| GET | `/api/v1/interfaces/{id}` | operator | Get interface detail |
| PUT | `/api/v1/interfaces/{id}/monitor` | admin | Toggle monitoring |
| GET | `/api/v1/interfaces/{id}/traffic` | viewer | Get traffic history |

**GET /api/v1/interfaces/{id}/traffic – Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| period | string | 24h | `1h`, `6h`, `24h`, `7d`, `30d` |
| resolution | string | auto | `raw`, `hourly`, `daily` |

---

### 3.3 Queues API (`/api/v1/queues.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/queues?router_id={id}` | operator | List queues for router |
| GET | `/api/v1/queues/{id}` | operator | Get queue detail |
| PUT | `/api/v1/queues/{id}/monitor` | admin | Toggle monitoring |
| GET | `/api/v1/queues/{id}/traffic` | viewer | Get queue traffic history |

---

### 3.4 PPPoE API (`/api/v1/pppoe.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/pppoe?router_id={id}` | operator | List PPPoE sessions |
| GET | `/api/v1/pppoe/{id}` | operator | Get PPPoE user detail |
| GET | `/api/v1/pppoe/{id}/traffic` | viewer | Get session traffic |

---

### 3.5 Traffic API (`/api/v1/traffic.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/traffic/summary` | viewer | Overall traffic summary |
| GET | `/api/v1/traffic/top` | viewer | Top consumers |
| GET | `/api/v1/traffic/export` | operator | CSV export |

**GET /api/v1/traffic/summary – Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| router_id | int | null | Filter by router |
| period | string | 24h | Time period |
| type | string | interface | `interface`, `queue`, `pppoe` |

---

### 3.6 MRTG API (`/api/v1/mrtg.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/mrtg/configs` | operator | List MRTG configs |
| POST | `/api/v1/mrtg/generate/{router_id}` | admin | Generate MRTG config |
| GET | `/api/v1/mrtg/graph/{config_id}` | viewer | Get graph paths |
| DELETE | `/api/v1/mrtg/configs/{id}` | admin | Delete MRTG config |

---

### 3.7 SNMP API (`/api/v1/snmp.php`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/v1/snmp/test` | admin | Test SNMP connectivity |
| GET | `/api/v1/snmp/walk/{router_id}` | admin | SNMP walk result |
| GET | `/api/v1/snmp/interfaces/{router_id}` | operator | Get live interface data |

---

## 4. Database Table Descriptions

### `routers`
Stores MikroTik router connection details and system information.  
Primary store for all SNMP targets. Status can be `active`, `inactive`, `error`.  
SNMP community string should be treated as sensitive – store with care.

### `interfaces`
All network interfaces discovered via `ifTable` SNMP walk (OID `.1.3.6.1.2.1.2.2`).  
`if_index` matches the SNMP ifIndex value. `monitored` flag controls polling.  
Admin_status and oper_status are 1=up, 2=down, 3=testing.

### `simple_queues`
MikroTik Simple Queue entries from MikroTik MIB `.1.3.6.1.4.1.14988.1.1.2`.  
`queue_index` is the MIB index. Limits stored in bits/s. `monitored` flag controls polling.

### `pppoe_users`
PPPoE active sessions from MikroTik MIB `.1.3.6.1.4.1.14988.1.1.11`.  
Records are upserted on each poll cycle. Status: `connected`, `disconnected`.

### `traffic_data`
Raw 5-minute traffic counters. Polymorphic: `target_type` ∈ {interface, queue, pppoe}.  
`bytes_in` / `bytes_out` are delta bytes since last poll (not cumulative).  
High cardinality table – purge old data regularly via cleanup cron.

### `traffic_daily`
Aggregated daily totals from `traffic_data`. Pre-computed for report performance.  
`avg_bps_in`, `avg_bps_out` stored as float (bits per second average for the day).

### `traffic_weekly`
Aggregated weekly totals. `week_start` is the Monday of the ISO week.

### `traffic_monthly`
Aggregated monthly totals. `month` stored as `YYYY-MM-01` date.

### `mrtg_configs`
Tracks MRTG configuration files generated for each router.  
`filename` is the full path to the .cfg file. `status`: `active`, `pending`, `error`.

### `users`
Application user accounts. `role` controls access level.  
`password_hash` uses PHP `password_hash()` with `PASSWORD_BCRYPT`.  
`status`: `active`, `inactive`, `locked`.

### `settings`
Global application configuration as key-value pairs.  
`key` is unique. `value` is TEXT to accommodate JSON arrays.

### `audit_log`
Immutable audit trail. Written on every create/update/delete operation.  
`action`: `create`, `update`, `delete`, `login`, `logout`, `export`.  
Never DELETE from this table – use archiving instead.

### `login_attempts`
Tracks login attempts for brute-force protection.  
Checked before authenticating: if ≥5 failures in 15 min, deny login.

---

## 5. SNMP OID Reference for MikroTik

### 5.1 Standard MIB-II OIDs (RFC 1213)

| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.2.1.1.1.0` | sysDescr | System description string |
| `.1.3.6.1.2.1.1.2.0` | sysObjectID | Enterprise OID |
| `.1.3.6.1.2.1.1.3.0` | sysUpTime | System uptime (hundredths of seconds) |
| `.1.3.6.1.2.1.1.4.0` | sysContact | System contact |
| `.1.3.6.1.2.1.1.5.0` | sysName | System name (hostname/identity) |
| `.1.3.6.1.2.1.1.6.0` | sysLocation | System location |

### 5.2 Interface MIB (IF-MIB, RFC 2863)

| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.2.1.2.1.0` | ifNumber | Total number of interfaces |
| `.1.3.6.1.2.1.2.2.1.1.{n}` | ifIndex | Interface index |
| `.1.3.6.1.2.1.2.2.1.2.{n}` | ifDescr | Interface description |
| `.1.3.6.1.2.1.2.2.1.3.{n}` | ifType | Interface type |
| `.1.3.6.1.2.1.2.2.1.4.{n}` | ifMtu | MTU |
| `.1.3.6.1.2.1.2.2.1.5.{n}` | ifSpeed | Speed in bits/s (max 4.29Gbps) |
| `.1.3.6.1.2.1.2.2.1.6.{n}` | ifPhysAddress | MAC address |
| `.1.3.6.1.2.1.2.2.1.7.{n}` | ifAdminStatus | 1=up, 2=down, 3=testing |
| `.1.3.6.1.2.1.2.2.1.8.{n}` | ifOperStatus | 1=up, 2=down, 3=testing |
| `.1.3.6.1.2.1.2.2.1.10.{n}` | ifInOctets | Input bytes (32-bit counter) |
| `.1.3.6.1.2.1.2.2.1.16.{n}` | ifOutOctets | Output bytes (32-bit counter) |
| `.1.3.6.1.2.1.2.2.1.14.{n}` | ifInErrors | Input errors |
| `.1.3.6.1.2.1.2.2.1.20.{n}` | ifOutErrors | Output errors |
| `.1.3.6.1.2.1.31.1.1.1.1.{n}` | ifName | Interface name string |
| `.1.3.6.1.2.1.31.1.1.1.6.{n}` | ifHCInOctets | Input bytes (64-bit HC) |
| `.1.3.6.1.2.1.31.1.1.1.10.{n}` | ifHCOutOctets | Output bytes (64-bit HC) |
| `.1.3.6.1.2.1.31.1.1.1.15.{n}` | ifHighSpeed | Speed in Mbps |
| `.1.3.6.1.2.1.31.1.1.1.18.{n}` | ifAlias | Interface alias/description |

### 5.3 MikroTik Enterprise MIB (`.1.3.6.1.4.1.14988`)

#### 5.3.1 System Information
| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.4.1.14988.1.1.4.1.0` | mtxrFirmwareVersion | RouterOS version |
| `.1.3.6.1.4.1.14988.1.1.4.4.0` | mtxrSerialNumber | Board serial number |
| `.1.3.6.1.4.1.14988.1.1.4.17.0` | mtxrBoardName | Board/model name |
| `.1.3.6.1.4.1.14988.1.1.7.1.0` | mtxrCPUFrequency | CPU frequency (MHz) |
| `.1.3.6.1.4.1.14988.1.1.7.5.0` | mtxrCPULoad | CPU load percentage |
| `.1.3.6.1.4.1.14988.1.1.7.2.0` | mtxrMemoryAvailable | Free RAM (bytes) |
| `.1.3.6.1.4.1.14988.1.1.7.3.0` | mtxrMemoryTotal | Total RAM (bytes) |

#### 5.3.2 Simple Queues (`.1.3.6.1.4.1.14988.1.1.2`)
| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.4.1.14988.1.1.2.1.1.1.{n}` | mtxrQueueSimpleIndex | Queue index |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.2.{n}` | mtxrQueueSimpleName | Queue name |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.3.{n}` | mtxrQueueSimpleSrcAddr | Source address / target |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.6.{n}` | mtxrQueueSimpleRate | Current rate (bits/s) |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.7.{n}` | mtxrQueueSimpleMaxLimit | Max limit (bits/s) |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.8.{n}` | mtxrQueueSimpleBytesIn | Total bytes in |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.9.{n}` | mtxrQueueSimpleBytesOut | Total bytes out |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.10.{n}` | mtxrQueueSimplePacketsIn | Total packets in |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.11.{n}` | mtxrQueueSimplePacketsOut | Total packets out |

#### 5.3.3 PPPoE Active Users (`.1.3.6.1.4.1.14988.1.1.11`)
| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.4.1.14988.1.1.11.1.1.1.{n}` | mtxrPPPoESessionIndex | Session index |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.2.{n}` | mtxrPPPoESessionName | Username |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.3.{n}` | mtxrPPPoESessionService | Service name |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.4.{n}` | mtxrPPPoESessionCaller | Caller ID (MAC) |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.5.{n}` | mtxrPPPoESessionAddress | Assigned IP |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.7.{n}` | mtxrPPPoESessionUptime | Session uptime (seconds) |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.8.{n}` | mtxrPPPoESessionBytesIn | Bytes received |
| `.1.3.6.1.4.1.14988.1.1.11.1.1.9.{n}` | mtxrPPPoESessionBytesOut | Bytes sent |

#### 5.3.4 Wireless (`.1.3.6.1.4.1.14988.1.1.1`)
| OID | Name | Description |
|-----|------|-------------|
| `.1.3.6.1.4.1.14988.1.1.1.1.1.5.{n}` | mtxrWlStatTxRate | Wireless TX rate |
| `.1.3.6.1.4.1.14988.1.1.1.1.1.6.{n}` | mtxrWlStatRxRate | Wireless RX rate |
| `.1.3.6.1.4.1.14988.1.1.1.1.1.11.{n}` | mtxrWlStatSignalStrength | Signal strength (dBm) |

---

## 6. File Structure Map

```
/var/www/noc/                              ← APP_DIR
│
├── public/                                ← Apache DocumentRoot
│   ├── index.php                          ← Front controller / entry point
│   ├── .htaccess                          ← URL rewriting, security headers
│   ├── assets/
│   │   ├── css/
│   │   │   ├── bootstrap.min.css
│   │   │   ├── app.css                    ← Custom styles
│   │   │   └── dark-theme.css
│   │   ├── js/
│   │   │   ├── bootstrap.bundle.min.js
│   │   │   ├── chart.min.js
│   │   │   ├── datatables.min.js
│   │   │   └── app.js                     ← Custom JS, AJAX helpers
│   │   └── img/
│   │       ├── logo.png
│   │       └── favicon.ico
│   └── mrtg/                              ← Symlink → /var/www/mrtg
│
├── api/
│   └── v1/
│       ├── routers.php
│       ├── interfaces.php
│       ├── queues.php
│       ├── pppoe.php
│       ├── traffic.php
│       ├── mrtg.php
│       └── snmp.php
│
├── core/
│   ├── App.php
│   ├── Router.php
│   ├── Database.php
│   ├── Auth.php
│   ├── Session.php
│   ├── Config.php
│   ├── Logger.php
│   ├── Snmp.php
│   ├── MrtgManager.php
│   ├── Response.php
│   └── Validator.php
│
├── modules/
│   ├── dashboard/
│   │   ├── DashboardController.php
│   │   └── views/
│   │       └── index.php
│   ├── routers/
│   │   ├── RouterController.php
│   │   ├── RouterModel.php
│   │   ├── RouterDiscovery.php
│   │   └── views/
│   │       ├── list.php
│   │       ├── add.php
│   │       ├── edit.php
│   │       └── show.php
│   ├── interfaces/
│   │   ├── InterfaceController.php
│   │   ├── InterfaceModel.php
│   │   └── views/
│   │       ├── list.php
│   │       └── show.php
│   ├── queues/
│   │   ├── QueueController.php
│   │   ├── QueueModel.php
│   │   └── views/
│   │       ├── list.php
│   │       └── show.php
│   ├── pppoe/
│   │   ├── PppoeController.php
│   │   ├── PppoeModel.php
│   │   └── views/
│   │       ├── list.php
│   │       └── show.php
│   ├── mrtg/
│   │   ├── MrtgController.php
│   │   ├── MrtgConfigGenerator.php
│   │   └── views/
│   │       ├── list.php
│   │       ├── view.php
│   │       └── graphs.php
│   ├── reports/
│   │   ├── ReportController.php
│   │   ├── ReportExporter.php
│   │   └── views/
│   │       ├── traffic.php
│   │       └── summary.php
│   ├── settings/
│   │   ├── SettingsController.php
│   │   ├── SettingsModel.php
│   │   └── views/
│   │       └── index.php
│   └── users/
│       ├── UserController.php
│       ├── UserModel.php
│       └── views/
│           ├── list.php
│           ├── add.php
│           └── edit.php
│
├── views/
│   ├── layouts/
│   │   ├── main.php                       ← Main HTML layout with navbar
│   │   └── auth.php                       ← Login page layout
│   └── partials/
│       ├── navbar.php
│       ├── sidebar.php
│       ├── footer.php
│       └── alerts.php
│
├── cron/
│   ├── poll_snmp.php                      ← Main SNMP polling script
│   ├── aggregate_hourly.php               ← Aggregate raw → hourly
│   ├── aggregate_daily.php                ← Aggregate hourly → daily
│   ├── aggregate_weekly.php               ← Aggregate daily → weekly
│   └── cleanup.php                        ← Purge old raw traffic data
│
├── config/
│   ├── .env                               ← Environment variables (not in git)
│   ├── .env.example                       ← Template for .env
│   ├── app.php                            ← Application config (reads .env)
│   └── database.php                       ← Database config
│
├── database/
│   ├── schema.sql                         ← Full DB schema
│   └── seed.sql                           ← Default seed data
│
├── logs/                                  ← Symlink → /var/log/noc
│
├── snmp/
│   ├── mibs/                              ← MikroTik MIB files
│   │   ├── MIKROTIK-MIB.txt
│   │   └── MT-PPPOE-MIB.txt
│   └── templates/
│       └── mikrotik_mrtg.cfg.tmpl         ← MRTG config template
│
├── mrtg/                                  ← MRTG configs (symlink → /etc/mrtg)
│
└── docs/
    ├── ARCHITECTURE.md
    ├── BLUEPRINT.md
    ├── INSTALL.md
    └── DEPLOY.md

/var/www/mrtg/                             ← MRTG_DIR (MRTG output)
├── graphs/
│   ├── router_1/
│   │   ├── iface_1_day.png
│   │   ├── iface_1_week.png
│   │   └── iface_1_month.png
│   └── router_2/
│       └── ...
└── data/
    └── router_1/
        └── iface_1.log

/etc/mrtg/                                 ← MRTG_CFG
├── mrtg.cfg                               ← Global MRTG config
├── router_1.cfg                           ← Per-router MRTG config
└── router_2.cfg

/var/log/noc/                              ← LOG_DIR
├── app.log
├── error.log
├── snmp.log
└── audit.log
```

---

## 7. Configuration Reference (`.env`)

```ini
# Application
APP_NAME="NOC MRTG Manager"
APP_VERSION="1.0.0"
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

# SNMP defaults
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
```
