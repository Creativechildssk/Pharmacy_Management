# Backup and Restore

## Manual backup

From the project root:

```bash
php scripts/backup.php
```

Successful backups are written to `storage/backups/pharmacy-YYYYMMDD-HHMMSS.sql`. This directory is git-ignored.

## Scheduled backup

### Windows Task Scheduler

Create a daily task that runs:

```text
C:\php\php.exe C:\Pharmacy_Management\scripts\backup.php
```

Use a service account that can read `config/app.php` and write `storage/backups/`.

### Linux cron

Example daily 19:00 schedule:

```cron
0 19 * * * /usr/bin/php /opt/Pharmacy_Management/scripts/backup.php >> /var/log/pharmacy-backup.log 2>&1
```

## Backup retention

Keep multiple generations. A practical local policy is daily backups for 30 days plus a weekly copy to a second physical device/NAS. Do not rely on the live server disk as the only copy.

## Restore test

Always validate a backup on a test database first:

```bash
mysql -u root -p -e "CREATE DATABASE pharmacy_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p pharmacy_restore_test < storage/backups/pharmacy-YYYYMMDD-HHMMSS.sql
```

Then inspect key totals: users, medicines, batches, receipts, dispenses and stock transactions.

## Production restore

1. Take the pharmacy application offline or restrict access.
2. Take a final backup of the current database if possible.
3. Create a clean target database.
4. Import the selected validated `.sql` file.
5. Point `config/app.php` at the restored database if the DB name changed.
6. Verify login, current inventory, a patient history and the stock ledger before reopening access.

Never import an untested backup directly over the only production database.
