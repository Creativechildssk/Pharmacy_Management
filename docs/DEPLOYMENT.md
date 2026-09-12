# Deployment Guide

## Recommended production shape

Use one dedicated Windows or Ubuntu machine on the estate LAN as the pharmacy server. Install Apache, PHP 8.1+ and MySQL/MariaDB. Give the server a stable LAN IP or DHCP reservation. Client computers use a browser and do not require PHP/MySQL locally.

## 1. Prerequisites

- PHP 8.1 or newer with PDO MySQL
- Apache 2.4+
- MySQL 8+ or MariaDB 10.6+
- Composer 2
- `mysqldump` for backups
- `curl` once during setup for frontend libraries, or manually copy the same libraries into `public/assets/vendor/`

## 2. Application setup

```bash
git clone https://github.com/Creativechildssk/Pharmacy_Management.git
cd Pharmacy_Management
composer install --no-dev --classmap-authoritative
cp config/app.example.php config/app.php
```

Edit `config/app.php` with the production DB credentials. Do not commit this file.

## 3. Database

Create a least-privilege database user rather than using `root` from the web application.

```sql
CREATE DATABASE pharmacy_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pharmacy_user'@'localhost' IDENTIFIED BY 'use-a-long-local-password';
GRANT SELECT,INSERT,UPDATE,DELETE ON pharmacy_management.* TO 'pharmacy_user'@'localhost';
FLUSH PRIVILEGES;
```

Import all three database setup files in this order:

```bash
mysql -u root -p pharmacy_management < database/schema.sql
mysql -u root -p pharmacy_management < database/seed.sql
mysql -u root -p pharmacy_management < database/audit_triggers.sql
```

The audit triggers make prescription creation, dispensing, reversals, and stock-adjustment status changes part of the same database transaction as the operational record.

## 4. Frontend libraries

Run once while the setup machine has internet access:

```bash
bash scripts/install_vendor_assets.sh
```

After these files are present under `public/assets/vendor/`, normal operation requires no internet.

## 5. First administrator

```bash
php scripts/create_admin.php admin "Pharmacy Administrator" "Use-A-Unique-Long-Password"
```

Change the example password text above. The CLI stores only the hash.

## 6. Apache

The web document root **must be the repository's `public/` directory**. Do not point Apache to the repository root because `config/`, `database/`, `scripts/` and documentation must not be web-accessible.

Example concept:

```apache
<VirtualHost *:80>
    ServerName pharmacy.local
    DocumentRoot "C:/Pharmacy_Management/public"
    <Directory "C:/Pharmacy_Management/public">
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

Use the Linux path on Ubuntu.

## 7. LAN access

Give the server a stable address such as `192.168.x.x`. Allow inbound HTTP only from the estate LAN in the host firewall. Clients access `http://SERVER-IP/` or a local DNS name.

For sensitive deployments, install a local TLS certificate and serve HTTPS even on the LAN.

## 8. Runtime permissions

The web-server account needs read access to the application and write access only where required. `storage/logs/` and `storage/backups/` may need local service-account permissions. Do not make the whole repository world-writable.

## 9. Production checklist

- `config/app.php` contains a unique DB password.
- Apache document root is `public/`.
- Directory listing is disabled.
- Default/admin password has been changed.
- Only required users/roles exist.
- The machine clock/timezone is correct (`Asia/Kolkata`).
- Browser clients can reach the server over LAN.
- A stock receipt and test dispense have been completed with sample data.
- Backup and restore have been tested on a non-production DB.
