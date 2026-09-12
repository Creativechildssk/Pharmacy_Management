# Estate Pharmacy Management System

A local-network estate dispensary application developed by **WFd DeepTech Labs Private Limited**.

## What V1 does

- Employee and external-patient records
- Visiting-doctor prescription entry by pharmacy staff
- Batch-wise medicine inventory
- FEFO (First Expiry, First Out) dispensing
- Expired-stock blocking
- Stock receipts with supplier/invoice/batch/expiry data
- Damage, expiry, supplier-return and controlled adjustment transactions
- Immutable stock movement ledger
- Operational and cost/value reports
- CSV exports
- Admin, Pharmacist, Store/Inventory and Management Viewer roles
- Audit logging foundation
- Local database backup script
- Localhost / estate LAN deployment

## Stack

PHP 8.1+, MySQL/MariaDB, Apache, Bootstrap 5, SweetAlert2, DataTables and vanilla JavaScript.

## Quick start

```bash
composer install
cp config/app.example.php config/app.php
# edit config/app.php with local DB credentials
mysql -u root -p -e "CREATE DATABASE pharmacy_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p pharmacy_management < database/schema.sql
mysql -u root -p pharmacy_management < database/seed.sql
bash scripts/install_vendor_assets.sh
php scripts/create_admin.php admin "Pharmacy Administrator" "ChangeThisPassword123!"
```

Point Apache's document root to the repository's `public/` directory. Never expose the repository root as the web document root.

For a quick development check:

```bash
php -S 127.0.0.1:8080 -t public
```

Then open `http://127.0.0.1:8080`.

## Inventory rule

The application has no supported UI path that directly edits stock quantity. Stock changes through posted receipts, dispensing, adjustments/write-offs, returns and reversal transactions. Dispensing checks non-expired batches and consumes the earliest valid expiry first.

## Security

- Passwords use PHP `password_hash()` / `password_verify()`.
- SQL uses PDO prepared statements for request values.
- State-changing forms use session CSRF tokens.
- Navigation and protected routes enforce roles server-side.
- `config/app.php` is git-ignored.
- The production web root must be `public/`.

## Documentation

- Design: `docs/superpowers/specs/2026-09-12-pharmacy-management-design.md`
- Implementation plan: `docs/superpowers/plans/2026-09-12-pharmacy-management-v1.md`
- Deployment: `docs/DEPLOYMENT.md`
- Backup/restore: `docs/BACKUP_RESTORE.md`

## Deferred roadmap

Purchase requests/indents, approvals, purchase orders, multi-store inventory, HR/ERP integration, doctor self-service, mobile apps and central-server sync are intentionally outside V1.
