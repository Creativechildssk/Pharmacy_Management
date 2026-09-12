# Fully Offline USB Installation

This deployment is designed for a pharmacy computer with **no internet or network access**. All runtime PHP dependencies, Bootstrap, SweetAlert2, DataTables, and database setup files must already be present in the USB bundle before it reaches the target computer.

## 1. Build the USB package on an internet-connected Windows PC

Open PowerShell in the repository root and run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build_offline_bundle.ps1
```

The builder creates an extracted folder and a ZIP under `dist/`.

The package includes:

- `app/` — production application files
- `app/vendor/` — Composer production dependencies
- `app/public/assets/vendor/` — Bootstrap, SweetAlert2, and DataTables stored locally
- `app/database/schema.sql`
- `app/database/seed.sql`
- `app/database/audit_triggers.sql`
- `INSTALL_OFFLINE_WINDOWS.bat` — double-click launcher
- `INSTALL_OFFLINE_WINDOWS.ps1` — installer engine
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

The installer is copied under `installer/`.

## 3. Prepare the isolated pharmacy computer

If XAMPP is not already installed:

1. Install XAMPP from the USB.
2. Start **MySQL** from the XAMPP Control Panel.
3. Apache can remain stopped until application installation is complete.

No internet connection is needed.

## 4. Install the pharmacy application

Extract the USB bundle to a normal local folder such as `C:\PharmacyUSB`.

Double-click:

```text
INSTALL_OFFLINE_WINDOWS.bat
```

The BAT launcher starts the PowerShell installer with a one-process execution-policy bypass, so you do not need to change the computer's permanent PowerShell policy.

The installer first verifies `SHA256SUMS.txt`, then asks interactively for:

1. **XAMPP path** — default `C:\xampp`
2. **MySQL administrator username** — default `root`
3. **MySQL administrator password** — hidden while typing; blank is allowed
4. **Database name** — default `pharmacy_management`
5. **Application DB username** — default `pharmacy_user`
6. **Application DB password** — hidden while typing; press Enter to auto-generate a strong password
7. **Application install path** — defaults to the XAMPP `htdocs\Pharmacy_Management` folder

The installer tests the MySQL administrator connection **before** creating or importing anything. If the credentials are wrong, installation stops safely and you can rerun the BAT file.

The application DB username must be different from the MySQL administrator username.

After confirmation, the installer:

- copies the production application
- creates the selected database
- creates/updates the least-privilege application DB user
- imports `schema.sql`
- imports `seed.sql`
- imports `audit_triggers.sql`
- writes the local `config/app.php`

Do not edit `INSTALL_OFFLINE_WINDOWS.ps1`, `INSTALL_OFFLINE_WINDOWS.bat`, or other files inside the verified bundle. Any change will correctly cause checksum verification to fail.

## 5. Create the first Admin user

After the installer completes, run the command it prints on screen. With the default XAMPP/application paths it is:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\Pharmacy_Management\scripts\create_admin.php admin "Pharmacy Administrator" "Choose-A-Strong-Password"
```

## 6. Start and open the system

Start **Apache** and **MySQL** from the XAMPP Control Panel, then open:

```text
http://localhost/Pharmacy_Management/public/
```

Normal pharmacy operation requires no internet connection and no LAN connection.

## 7. Backup on the isolated machine

Run:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\Pharmacy_Management\scripts\backup.php
```

Backups are stored under `storage\backups\`. Copy them periodically to a separate approved offline storage device.

## 8. Updating later

Do not connect the pharmacy computer to the internet for updates. Build and verify a new USB bundle on an internet-connected development computer, take a database backup, then transfer the approved release by USB.
