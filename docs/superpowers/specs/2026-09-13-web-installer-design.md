# Generic Web Installer Design

**Project:** Estate Pharmacy Management System  
**Repository:** `Creativechildssk/Pharmacy_Management`  
**Developer:** WFd DeepTech Labs Private Limited  
**Date:** 2026-09-13  
**Status:** Approved design baseline

## Purpose

Add a generic browser-based fresh-install wizard that works across XAMPP, WAMP, LAMP/Linux Apache, Nginx/PHP-FPM, IIS/PHP, and conventional PHP/MySQL hosting environments that satisfy the application's runtime requirements.

The installer must support both root-domain and subdirectory deployments, create the first application Administrator account, initialize the database safely, write application configuration, and permanently lock itself after successful installation.

## Architectural Decision

The installer is a standalone subsystem under `/install/` and does **not** depend on `config/bootstrap.php`, because the normal application bootstrap requires `config/app.php` to already exist.

The installer owns only fresh installation. Future upgrades and migrations are separate concerns and must not be implemented through this installer.

## Proposed Structure

```text
/install
├── index.php
├── bootstrap.php
├── Installer.php
├── EnvironmentChecker.php
├── DatabaseInstaller.php
├── ConfigWriter.php
├── AdminCreator.php
├── InstallState.php
└── templates/
    ├── layout.php
    ├── welcome.php
    ├── environment.php
    ├── url.php
    ├── database.php
    ├── system.php
    ├── admin.php
    ├── review.php
    ├── progress.php
    └── complete.php
```

Supporting application changes:

```text
/app/Support/Url.php
/config/app.example.php
/config/bootstrap.php
/public/index.php
/public/login.php
/public/logout.php
/templates/header.php
/templates/sidebar.php
/public/... affected forms/redirects/links
/tests/Installer/
/tests/Support/UrlTest.php
```

## Installation Flow

### 1. Welcome

Show product identity, developer branding, fresh-install warning, and current installation state.

If `storage/installed.lock` exists, installation is blocked and the page explains that the installer is disabled. The lock must only be removable manually by an authorized operator with filesystem access.

### 2. Environment Check

Check and report:

- PHP >= 8.1
- PDO extension
- PDO MySQL extension
- JSON support
- mbstring support
- sessions available
- writable `config/`
- writable `storage/`
- readable `database/schema.sql`
- readable `database/seed.sql`
- readable `database/audit_triggers.sql`
- Composer autoloader present at `vendor/autoload.php`

Server software is informational only. The installer must not require Apache-specific features.

Blocking failures must prevent progression.

### 3. Application URL / Base Path

Auto-detect the current scheme, host, port, and path from the request.

Allow the operator to correct the detected value before continuing.

Examples that must work:

```text
https://example.com/
https://example.com/pharmacy/
http://localhost/Pharmacy_Management/public/
http://127.0.0.1:8080/
```

Persist a normalized `base_url` in `config/app.php`.

All application redirects, navigation links, assets, form actions, and login/logout URLs must be generated through one helper rather than hard-coded root-relative paths.

### 4. Database Mode

Provide two modes.

#### Existing Database

Fields:

- host
- port
- database name
- username
- password

The installer tests connection using PDO and proceeds only if successful.

#### Create Database/User

Fields:

- MySQL admin host
- admin port
- admin username
- admin password
- database name
- application DB username
- application DB password

The installer tests administrator connectivity first, then creates the database and application user with only the privileges required by the application.

Database and user identifiers must be validated against a conservative identifier pattern before interpolating into DDL.

Passwords must never be written to logs or rendered back into HTML after submission.

## Database Initialization

Installation order:

```text
connect
→ create DB/user if selected
→ import database/schema.sql
→ import database/seed.sql
→ import database/audit_triggers.sql
→ create first ADMIN user
→ persist system settings
→ write config/app.php
→ create storage/installed.lock
```

The installer must stop immediately on failure and show the failed stage without exposing secrets or stack traces.

The lock file is created only after all prior stages succeed.

For fresh installs, the installer must reject a database that already contains the application's core tables unless the database is empty and the user explicitly selected fresh installation.

## System Information

Collect:

- estate/company name
- application display name
- timezone
- session timeout minutes
- near-expiry thresholds (defaults 30, 60, 90)

Store runtime configuration in `config/app.php` and business settings in `system_settings` where appropriate.

## First Administrator

Collect:

- full name
- username
- password
- password confirmation

Requirements:

- username must be unique
- password minimum 10 characters
- password stored only using `password_hash(..., PASSWORD_DEFAULT)`
- assign the existing `ADMIN` role
- never echo or log the password

After install, the operator can sign in immediately with this account.

## Review Step

Before installation, show a summary of:

- application URL
- database host/port/name
- database mode
- application DB username
- estate/company name
- timezone
- administrator username/full name

Never show passwords.

## Config File

`config/app.php` must contain at minimum:

```php
return [
    'app' => [
        'name' => 'Estate Pharmacy Management System',
        'developer' => 'WFd DeepTech Labs Private Limited',
        'timezone' => 'Asia/Kolkata',
        'session_timeout_minutes' => 30,
        'base_url' => 'http://localhost/Pharmacy_Management/public',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'pharmacy_management',
        'username' => 'pharmacy_user',
        'password' => 'secret',
    ],
];
```

The file must be written atomically where possible and never committed to Git.

## URL Helper

Add one URL abstraction, for example:

```php
Url::to('/login.php')
```

Rules:

- combines configured `base_url` with application-relative path
- avoids duplicate slashes
- preserves scheme, host, port, and subdirectory path
- returns escaped values only at rendering boundaries, not inside the helper

All redirects and navigation links must use this helper.

## Security

- installer session uses CSRF protection
- session cookie is HttpOnly and SameSite=Strict
- HTTPS cookie flag enabled when request is HTTPS
- no credentials in query strings
- no passwords in logs, review pages, or error messages
- DB connection errors shown as generic operator-safe messages
- DDL identifiers strictly validated
- application values inserted with prepared statements
- installation blocked once `installed.lock` exists
- installer refuses to overwrite existing `config/app.php` during normal execution
- raw exception traces are never rendered in production mode

## Error Handling

Each step validates its own inputs before moving forward.

The install action reports stages such as:

```text
Environment validation
Database connection
Database creation
Schema import
Seed import
Audit trigger import
Administrator creation
System settings
Configuration file
Installation lock
```

If a stage fails, installation stops and the failed stage is identified without exposing passwords.

## Generic Hosting Requirements

The installer must not assume:

- XAMPP path
- Windows filesystem layout
- Apache document root
- shell/CLI access
- ability to edit virtual-host configuration

Shared-hosting users must be able to use the Existing Database mode.

Self-managed users may use Create Database/User mode if the supplied account has adequate privileges.

## Post-Install Behavior

On success:

- create `storage/installed.lock`
- show Login button generated via configured `base_url`
- `/install/` becomes inaccessible for installation
- normal application bootstrap loads `config/app.php`
- all navigation and redirects honor `base_url`

## Out of Scope

- application upgrades
- schema migrations between released versions
- importing legacy pharmacy databases
- restoring backups
- SSL certificate management
- web-server virtual-host configuration
- automatic DNS configuration
- automatic PHP/MySQL installation

## Acceptance Criteria

The feature is complete when:

- fresh installation can be completed entirely in a browser
- both database modes work
- first Admin account is created through the wizard
- no CLI command is required after install
- installation succeeds in a root deployment and a subdirectory deployment
- hard-coded root-relative application URLs are eliminated from runtime navigation/redirect paths
- installer locks itself after success
- config file and DB credentials are never exposed in Git or UI
- existing application test suite remains green
- new installer and URL-helper tests pass
