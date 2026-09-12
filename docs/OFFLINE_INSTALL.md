# Fully Offline USB Installation

This deployment is designed for a pharmacy computer with **no internet or network access**. All runtime PHP dependencies, Bootstrap, SweetAlert2, DataTables, and database setup files must already be present in the USB bundle before it reaches the target computer.

## 1. Build the USB package on an internet-connected Windows PC

Open PowerShell in the repository root and run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build_offline_bundle.ps1
```

The builder creates both an extracted folder and a ZIP under `dist/`, for example:

```text
Pharmacy_Management_Offline_USB_20260912-110000\
Pharmacy_Management_Offline_USB_20260912-110000.zip
```

The package includes:

- `app/` — production application files
- `app/vendor/` — Composer production dependencies
- `app/public/assets/vendor/` — Bootstrap, SweetAlert2, and DataTables stored locally
- `app/database/schema.sql`
- `app/database/seed.sql`
- `app/database/audit_triggers.sql`
- `INSTALL_OFFLINE_WINDOWS.ps1`
- `OFFLINE_INSTALL.md`
- `MANIFEST.json`
- `SHA256SUMS.txt`

Development-only folders such as `.git`, `.github`, and `tests` are excluded. `config/app.php` is also excluded so production database credentials are never copied from the build PC.

## 2. Optional: include the XAMPP installer on the USB

Download the required XAMPP Windows installer on the internet-connected PC and pass its path to the builder:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build_offline_bundle.ps1 `
  -XamppInstaller "C:\Installers\xampp-windows-x64-installer.exe"
```

The installer is copied into the bundle under `installer/`. The builder does not download or silently install XAMPP for you.

## 3. Copy the package to USB

Copy either the ZIP or the extracted bundle directory to a clean USB drive. Keep `SHA256SUMS.txt` with the bundle; the offline installer verifies all packaged files before installation.

For a controlled environment, label the USB with the bundle date and keep it as the installation master.

## 4. Prepare the isolated pharmacy computer

If XAMPP is not already installed:

1. Run the XAMPP installer from the USB.
2. Install to the normal location `C:\xampp` unless your IT policy requires another path.
3. Start **MySQL** from the XAMPP Control Panel.
4. Apache can remain stopped until the application installation is complete.

No internet connection is needed for these steps.

## 5. Install the pharmacy application from USB

Extract the bundle if necessary. Open **PowerShell as Administrator** in the extracted bundle folder and run:

```powershell
powershell -ExecutionPolicy Bypass -File .\INSTALL_OFFLINE_WINDOWS.ps1
```

The installer will:

1. Verify the USB package against `SHA256SUMS.txt`.
2. Confirm XAMPP PHP and MySQL exist locally.
3. Copy the production application to `C:\xampp\htdocs\Pharmacy_Management`.
4. Generate a random local database password.
5. Create the `pharmacy_management` database and least-privilege `pharmacy_user` account.
6. Import `database/schema.sql`.
7. Import `database/seed.sql`.
8. Import `database/audit_triggers.sql`.
9. Generate the local `config/app.php` file.

If your XAMPP MySQL root account has a password, supply it when launching the installer:

```powershell
powershell -ExecutionPolicy Bypass -File .\INSTALL_OFFLINE_WINDOWS.ps1 -MySqlRootPassword "YOUR-LOCAL-MYSQL-ROOT-PASSWORD"
```

Do not write the MySQL root password into documentation or leave it in a saved PowerShell command history on a shared computer.

## 6. Create the first Admin user

After the offline installer completes, run this from Command Prompt or PowerShell, replacing the example password:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\Pharmacy_Management\scripts\create_admin.php admin "Pharmacy Administrator" "Choose-A-Strong-Password"
```

The password is stored only as a PHP password hash in MySQL.

## 7. Start and open the system

Start **Apache** and **MySQL** from the XAMPP Control Panel.

Open the browser on the same isolated PC:

```text
http://localhost/Pharmacy_Management/public/
```

Sign in with the Admin account created in the previous step.

## 8. Offline operating model

Normal pharmacy use requires no internet connection and no LAN connection. The following functions remain local to the pharmacy computer:

- employee and external-patient records
- visiting-doctor prescriptions
- medicine and supplier masters
- stock receipts
- batch and expiry tracking
- FEFO dispensing
- stock adjustments and reversals
- audit logs
- reports and CSV exports
- database backups

The application does not need an external API, CDN, cloud database, or web service during normal operation.

## 9. Backup on an isolated machine

Run:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\Pharmacy_Management\scripts\backup.php
```

The SQL backup is created under:

```text
C:\xampp\htdocs\Pharmacy_Management\storage\backups\
```

Copy backups periodically to a separate approved encrypted USB drive or other offline storage. Do not keep the only backup on the same pharmacy computer.

## 10. Updating the isolated installation later

Do not connect the pharmacy computer to the internet just to update the application. Build a new signed/checksummed offline USB bundle on an internet-connected development computer, test it separately, then transfer the approved release by USB.

Before any future application update, take and verify a database backup first.
