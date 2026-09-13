# Generic Web Installer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a generic browser-based fresh-install wizard that supports root and subdirectory deployments, both existing-database and create-database modes, first-Admin creation, secure config generation, and post-install lockout.

**Architecture:** Implement a standalone `/install/` subsystem that does not depend on the normal application bootstrap until installation is complete. Introduce a single URL helper driven by configurable `base_url`, then migrate runtime redirects, navigation, assets, and form targets away from hard-coded root-relative URLs.

**Tech Stack:** PHP 8.1+, PDO/PDO MySQL, MySQL/MariaDB, PHPUnit, Bootstrap 5, existing application architecture.

**Spec:** `docs/superpowers/specs/2026-09-13-web-installer-design.md`

## Global Constraints

- PHP minimum version remains 8.1.
- Installer must work without assuming Windows, XAMPP, Apache, shell access, or a specific document root.
- Installer is for fresh installations only.
- Installer must support Existing Database and Create Database/User modes.
- First application Administrator must be created in the browser wizard.
- `storage/installed.lock` disables installation after success.
- `config/app.php` must never be committed.
- Passwords must never appear in query strings, logs, review pages, or raw error output.
- Application URLs must respect configured `base_url`.
- Existing pharmacy functionality and tests must remain green.

---

## File Structure

```text
/app/Support/Url.php
/install/index.php
/install/bootstrap.php
/install/Installer.php
/install/EnvironmentChecker.php
/install/DatabaseInstaller.php
/install/ConfigWriter.php
/install/AdminCreator.php
/install/InstallState.php
/install/templates/layout.php
/install/templates/welcome.php
/install/templates/environment.php
/install/templates/url.php
/install/templates/database.php
/install/templates/system.php
/install/templates/admin.php
/install/templates/review.php
/install/templates/progress.php
/install/templates/complete.php
/tests/Support/UrlTest.php
/tests/Installer/EnvironmentCheckerTest.php
/tests/Installer/InstallStateTest.php
/tests/Installer/ConfigWriterTest.php
/tests/Installer/DatabaseInstallerTest.php
/tests/Installer/AdminCreatorTest.php
/tests/Installer/InstallerTest.php
```

Existing runtime files to modify include `config/app.example.php`, `config/bootstrap.php`, `public/index.php`, `public/login.php`, `public/logout.php`, `templates/header.php`, `templates/sidebar.php`, and runtime pages containing root-relative application URLs.

---

### Task 1: Add Base URL Support and URL Helper

**Files:**
- Create: `app/Support/Url.php`
- Create: `tests/Support/UrlTest.php`
- Modify: `config/app.example.php`
- Modify: `config/bootstrap.php`

**Interfaces:**
- Produces: `Url::configure(string $baseUrl): void`
- Produces: `Url::to(string $path = ''): string`

- [ ] **Step 1: Write failing URL helper tests**

Test these exact cases:

```php
Url::configure('http://localhost/Pharmacy_Management/public');
self::assertSame('http://localhost/Pharmacy_Management/public/login.php', Url::to('/login.php'));

Url::configure('https://example.com/pharmacy/');
self::assertSame('https://example.com/pharmacy/dashboard.php', Url::to('dashboard.php'));

Url::configure('https://example.com');
self::assertSame('https://example.com/assets/css/app.css', Url::to('/assets/css/app.css'));
```

Also test duplicate slash removal and empty-path behavior.

- [ ] **Step 2: Run the test and verify RED**

Run: `vendor/bin/phpunit tests/Support/UrlTest.php`

Expected: FAIL because `Url` does not exist.

- [ ] **Step 3: Implement `Url`**

Implementation rules:

```php
private static string $baseUrl = '';

public static function configure(string $baseUrl): void
{
    self::$baseUrl = rtrim(trim($baseUrl), '/');
}

public static function to(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? self::$baseUrl . '/' : self::$baseUrl . '/' . $path;
}
```

Reject empty or malformed configured URLs with `InvalidArgumentException`.

- [ ] **Step 4: Add `base_url` to `config/app.example.php`**

Use:

```php
'base_url' => 'http://localhost/Pharmacy_Management/public',
```

- [ ] **Step 5: Configure `Url` during normal bootstrap**

After loading `$config`, call:

```php
Url::configure((string)($config['app']['base_url'] ?? ''));
```

- [ ] **Step 6: Run tests**

Run: `vendor/bin/phpunit tests/Support/UrlTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Support/Url.php tests/Support/UrlTest.php config
git commit -m "feat: add configurable application base url"
```

---

### Task 2: Migrate Runtime URLs Away from Root-Relative Paths

**Files:**
- Modify: `public/index.php`
- Modify: `public/login.php`
- Modify: `public/logout.php`
- Modify: `templates/header.php`
- Modify: `templates/sidebar.php`
- Modify: runtime PHP pages that contain application-local `href="/..."`, `src="/..."`, form actions, or `Location: /...`
- Create: `tests/Support/RuntimeUrlUsageTest.php`

**Interfaces:**
- Consumes: `Url::to(string $path): string`

- [ ] **Step 1: Write a repository-level failing test**

Scan runtime PHP under `public/` and `templates/` and fail on:

```text
header('Location: /
href="/
src="/
action="/
```

Allow external absolute URLs and protocol-relative URLs only when explicitly documented.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit tests/Support/RuntimeUrlUsageTest.php`

Expected: FAIL on current `/login.php`, `/dashboard.php`, `/assets/...` and navigation links.

- [ ] **Step 3: Replace redirects with `Url::to()`**

Example:

```php
header('Location: ' . Url::to('/login.php'));
```

- [ ] **Step 4: Replace HTML links/assets with escaped URL helper output**

Example:

```php
<a href="<?= htmlspecialchars(Url::to('/dashboard.php')) ?>">Dashboard</a>
```

- [ ] **Step 5: Run full URL usage test**

Run: `vendor/bin/phpunit tests/Support/RuntimeUrlUsageTest.php`

Expected: PASS.

- [ ] **Step 6: Manual smoke-check both deployment shapes**

Validate generated URLs for:

```text
http://localhost/
http://localhost/Pharmacy_Management/public/
```

- [ ] **Step 7: Commit**

```bash
git add public templates tests/Support/RuntimeUrlUsageTest.php
git commit -m "fix: make application urls base-path aware"
```

---

### Task 3: Build Standalone Installer Bootstrap and Installation State Guard

**Files:**
- Create: `install/bootstrap.php`
- Create: `install/InstallState.php`
- Create: `tests/Installer/InstallStateTest.php`

**Interfaces:**
- Produces: `InstallState::isInstalled(string $root): bool`
- Produces: `InstallState::lock(string $root): void`
- Produces: installer session + CSRF primitives without requiring `config/app.php`

- [ ] **Step 1: Write failing lock-state tests**

Test missing lock => false, present lock => true, lock creation => file exists.

- [ ] **Step 2: Verify RED**

Run: `vendor/bin/phpunit tests/Installer/InstallStateTest.php`

- [ ] **Step 3: Implement installer bootstrap**

Requirements:

```text
require vendor/autoload.php
start secure session
create installer CSRF token
set safe timezone fallback
never include config/bootstrap.php
```

- [ ] **Step 4: Implement `InstallState`**

Lock path:

```text
storage/installed.lock
```

Use exclusive creation and throw on write failure.

- [ ] **Step 5: Run tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add install/bootstrap.php install/InstallState.php tests/Installer/InstallStateTest.php
git commit -m "feat: add installer bootstrap and install lock"
```

---

### Task 4: Implement Environment Checks

**Files:**
- Create: `install/EnvironmentChecker.php`
- Create: `tests/Installer/EnvironmentCheckerTest.php`

**Interfaces:**
- Produces: `EnvironmentChecker::check(string $root): array`
- Each result row: `array{key:string,label:string,ok:bool,required:bool,detail:string}`
- Produces: `EnvironmentChecker::canContinue(array $checks): bool`

- [ ] **Step 1: Write failing environment tests**

Test required checks for PHP version, PDO, PDO MySQL, JSON, sessions, writable config/storage, schema/seed/trigger readability, and Composer autoloader.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Implement checker using dependency-testable callbacks where needed**

Do not hard-code XAMPP or Apache checks.

- [ ] **Step 4: Ensure mbstring is reported clearly**

Treat mbstring as required to match intended production environment.

- [ ] **Step 5: Run tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add install/EnvironmentChecker.php tests/Installer/EnvironmentCheckerTest.php
git commit -m "feat: add installer environment validation"
```

---

### Task 5: Implement Application URL Detection and Validation

**Files:**
- Create: `install/UrlDetector.php`
- Create: `tests/Installer/UrlDetectorTest.php`

**Interfaces:**
- Produces: `UrlDetector::detect(array $server): string`
- Produces: `UrlDetector::validate(string $url): string`

- [ ] **Step 1: Write tests for localhost, HTTPS, ports, and subdirectories**

Examples:

```text
http://localhost/Pharmacy_Management/public
https://example.com/pharmacy
http://127.0.0.1:8080
```

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Implement detection from HTTPS/host/request path**

Strip `/install` from the installer path when deriving the application base path.

- [ ] **Step 4: Validate scheme is only http/https and host is present**

- [ ] **Step 5: Run tests**

- [ ] **Step 6: Commit**

```bash
git add install/UrlDetector.php tests/Installer/UrlDetectorTest.php
git commit -m "feat: detect installer application url"
```

---

### Task 6: Implement Database Connection and Initialization Service

**Files:**
- Create: `install/DatabaseInstaller.php`
- Create: `tests/Installer/DatabaseInstallerTest.php`

**Interfaces:**
- Produces: `DatabaseInstaller::testExisting(array $config): void`
- Produces: `DatabaseInstaller::prepareDatabase(array $adminConfig, array $appConfig): void`
- Produces: `DatabaseInstaller::assertFreshDatabase(PDO $pdo): void`
- Produces: `DatabaseInstaller::importSqlFile(PDO $pdo, string $file): void`

- [ ] **Step 1: Write failing identifier-validation tests**

Accept:

```text
pharmacy_management
pharmacy_user
estate2026
```

Reject spaces, quotes, backticks, semicolons, slashes, and control characters.

- [ ] **Step 2: Write existing-DB connectivity test**

Use CI MySQL credentials.

- [ ] **Step 3: Write fresh-database guard test**

A DB containing `users` or `medicines` must be rejected as already initialized.

- [ ] **Step 4: Verify RED**

- [ ] **Step 5: Implement PDO connection creation with native prepares**

- [ ] **Step 6: Implement optional database/user creation**

Only validated identifiers may be interpolated into DDL. Password strings must be safely quoted for MySQL account creation.

- [ ] **Step 7: Implement SQL file imports**

Import in order: schema, seed, audit triggers.

- [ ] **Step 8: Run integration tests**

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add install/DatabaseInstaller.php tests/Installer/DatabaseInstallerTest.php
git commit -m "feat: add web installer database initialization"
```

---

### Task 7: Implement Config Writer

**Files:**
- Create: `install/ConfigWriter.php`
- Create: `tests/Installer/ConfigWriterTest.php`

**Interfaces:**
- Produces: `ConfigWriter::write(string $path, array $app, array $database): void`

- [ ] **Step 1: Write failing config-output tests**

Assert output is valid PHP returning expected values including `base_url`.

- [ ] **Step 2: Add overwrite-protection test**

Existing `config/app.php` must cause refusal.

- [ ] **Step 3: Verify RED**

- [ ] **Step 4: Implement atomic write**

Write a temporary file in the same directory, then rename it to `app.php`.

- [ ] **Step 5: Escape values using `var_export()`**

Do not manually concatenate quoted secrets.

- [ ] **Step 6: Run tests**

- [ ] **Step 7: Commit**

```bash
git add install/ConfigWriter.php tests/Installer/ConfigWriterTest.php
git commit -m "feat: add secure installer config writer"
```

---

### Task 8: Implement First Administrator Creation and System Settings

**Files:**
- Create: `install/AdminCreator.php`
- Create: `tests/Installer/AdminCreatorTest.php`

**Interfaces:**
- Produces: `AdminCreator::create(PDO $pdo, string $username, string $fullName, string $password): int`
- Produces: `AdminCreator::saveSystemSettings(PDO $pdo, array $settings): void`

- [ ] **Step 1: Write failing admin tests**

Assert:

```text
password shorter than 10 rejected
blank username rejected
ADMIN role assigned
stored password is not plaintext
password_verify returns true
```

- [ ] **Step 2: Write settings tests**

Verify estate name, session timeout, and 30/60/90 expiry thresholds.

- [ ] **Step 3: Verify RED**

- [ ] **Step 4: Implement with prepared statements and `password_hash()`**

- [ ] **Step 5: Run tests**

- [ ] **Step 6: Commit**

```bash
git add install/AdminCreator.php tests/Installer/AdminCreatorTest.php
git commit -m "feat: create first admin during web install"
```

---

### Task 9: Implement Installation Orchestrator

**Files:**
- Create: `install/Installer.php`
- Create: `tests/Installer/InstallerTest.php`

**Interfaces:**
- Produces: `Installer::install(array $command): array{success:bool,stage:string,message:string}`

- [ ] **Step 1: Write failing stage-order test**

Assert order:

```text
connect
create database/user if selected
fresh DB guard
schema
seed
audit triggers
admin
settings
config
lock
```

- [ ] **Step 2: Write failure-stage test**

A schema failure must return stage `schema` and must not create `installed.lock`.

- [ ] **Step 3: Verify RED**

- [ ] **Step 4: Implement orchestrator**

Keep operator-safe messages and log no secrets.

- [ ] **Step 5: Ensure lock is last operation**

- [ ] **Step 6: Run tests**

- [ ] **Step 7: Commit**

```bash
git add install/Installer.php tests/Installer/InstallerTest.php
git commit -m "feat: orchestrate browser installation"
```

---

### Task 10: Build Browser Wizard UI

**Files:**
- Create: `install/index.php`
- Create all `install/templates/*.php`
- Create: `install/assets/install.css`

**Interfaces:**
- Consumes installer services from Tasks 3-9.

- [ ] **Step 1: Implement step state in installer session**

Allowed steps:

```text
welcome
environment
url
database
system
admin
review
install
complete
```

- [ ] **Step 2: Add CSRF validation to every POST**

- [ ] **Step 3: Build Welcome and Environment pages**

Blocking checks disable Continue.

- [ ] **Step 4: Build URL page**

Pre-fill detector result and allow correction.

- [ ] **Step 5: Build Database page**

Use radio selection for Existing DB vs Create DB/User and dynamically show appropriate fields.

Passwords use `<input type="password">` and are never repopulated into HTML after validation failure.

- [ ] **Step 6: Build System page**

Collect estate name, app name, timezone, timeout, 30/60/90 thresholds.

- [ ] **Step 7: Build Admin page**

Collect full name, username, password, confirmation.

- [ ] **Step 8: Build Review page**

Show non-secret summary only.

- [ ] **Step 9: Build Install/Complete pages**

On success show a Login button using the configured application URL.

- [ ] **Step 10: Add installed-lock refusal screen**

Any request after installation shows “Installer disabled” without form actions.

- [ ] **Step 11: Run PHP syntax validation**

Run:

```bash
find install -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 12: Commit**

```bash
git add install
git commit -m "feat: add browser installation wizard"
```

---

### Task 11: Add End-to-End Fresh Install Integration Test

**Files:**
- Create: `tests/Integration/WebInstallerWorkflowTest.php`

**Interfaces:**
- Verifies the full fresh-install service workflow against MySQL.

- [ ] **Step 1: Create a temporary empty test database**

- [ ] **Step 2: Execute installer service with Existing Database mode**

Use temporary config/lock paths so repository files are untouched.

- [ ] **Step 3: Assert core tables, ADMIN role, first user, settings, config, and lock**

- [ ] **Step 4: Assert generated base URL works through `Url::to()`**

- [ ] **Step 5: Assert second installation attempt is blocked**

- [ ] **Step 6: Run the test**

Run: `vendor/bin/phpunit tests/Integration/WebInstallerWorkflowTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add tests/Integration/WebInstallerWorkflowTest.php
git commit -m "test: cover complete web installer workflow"
```

---

### Task 12: Update Offline Bundle and Deployment Documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/DEPLOYMENT.md`
- Modify: `docs/OFFLINE_INSTALL.md`
- Modify: `scripts/build_offline_bundle.ps1`
- Modify: offline-bundle contract tests if required

**Interfaces:**
- USB bundle includes `/install/` automatically as part of runtime application.

- [ ] **Step 1: Document browser-first installation**

Preferred flow:

```text
copy/upload files
ensure config/storage writable
open /install/
complete wizard
open login
```

- [ ] **Step 2: Keep CLI/PowerShell installer documented as optional alternative**

Do not remove existing offline installer tooling.

- [ ] **Step 3: Document root and subdirectory examples**

- [ ] **Step 4: Run offline bundle contract tests**

- [ ] **Step 5: Build Windows artifact and verify `/install/index.php` exists in generated ZIP**

- [ ] **Step 6: Commit**

```bash
git add README.md docs scripts tests/OfflineBundle
git commit -m "docs: add browser installer deployment flow"
```

---

### Task 13: Final Security and Acceptance Verification

**Files:**
- Modify only files required by findings.
- Update: `docs/ACCEPTANCE_TESTS.md`

- [ ] **Step 1: Run complete test suite**

Run: `vendor/bin/phpunit`

Expected: PASS.

- [ ] **Step 2: Run PHP syntax check across application and installer**

- [ ] **Step 3: Search for runtime hard-coded root-relative URLs**

Expected: none in application-local runtime paths.

- [ ] **Step 4: Verify secrets are absent from repository**

Check `config/app.php` is ignored and no test fixture contains real credentials.

- [ ] **Step 5: Verify lockout behavior manually**

After test install, visiting `/install/` must show disabled state.

- [ ] **Step 6: Verify both deployment shapes**

```text
root deployment
subdirectory deployment
```

- [ ] **Step 7: Update acceptance documentation with actual evidence**

- [ ] **Step 8: Commit**

```bash
git add docs/ACCEPTANCE_TESTS.md
git commit -m "test: verify generic web installer acceptance"
```

---

## Review Gates

```text
Gate A: Tasks 1-2 — URL/base-path correctness
Gate B: Tasks 3-5 — installer bootstrap/environment/URL detection
Gate C: Tasks 6-9 — database/config/admin/orchestration security
Gate D: Task 10 — browser wizard
Gate E: Tasks 11-13 — integration, packaging, acceptance
```

## Definition of Done

The feature is done only when:

- `/install/` works before `config/app.php` exists.
- Existing Database mode succeeds.
- Create Database/User mode succeeds when privileges permit.
- First Admin is created in-browser.
- No CLI step is required after install.
- Root and subdirectory deployments generate correct links and redirects.
- No normal runtime path depends on hard-coded `/login.php` or `/dashboard.php` style root URLs.
- `config/app.php` is created securely and atomically.
- `storage/installed.lock` is created only after successful installation.
- Installer refuses rerun after lock creation.
- Passwords are absent from rendered review/error output.
- Full PHPUnit and MySQL integration suite passes.
- Offline USB package still builds and contains the web installer.
