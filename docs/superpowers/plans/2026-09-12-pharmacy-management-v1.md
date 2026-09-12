# Pharmacy Management V1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a localhost/LAN estate pharmacy and dispensary management system for WFd DeepTech Labs with employee and external-patient dispensing, visiting-doctor prescription capture, batch-wise FEFO inventory, expiry control, auditability, and management reporting.

**Architecture:** Use a modular PHP 8.x monolith backed by MySQL/MariaDB. Inventory changes are transaction-ledger driven: stock receipts add quantity, dispensing and write-offs subtract quantity, and no UI path directly edits stock balances. Server-rendered Bootstrap 5 pages are enhanced with DataTables, SweetAlert2, and small AJAX endpoints where faster interaction materially improves pharmacy workflows.

**Tech Stack:** PHP 8.x, MySQL/MariaDB, Apache, Bootstrap 5, SweetAlert2, DataTables, Vanilla JavaScript, AJAX, PHPUnit, PDO.

**Spec:** `docs/superpowers/specs/2026-09-12-pharmacy-management-design.md`

## Global Constraints

- Day-to-day operation must not require internet access.
- Production use is localhost / estate LAN.
- Product branding must identify **WFd DeepTech Labs Private Limited** as developer.
- Operating company / estate name must be configurable, not hard-coded.
- Employee and external patient workflows are both required.
- Visiting doctors do not receive V1 logins; pharmacy staff enters their prescriptions.
- All inventory is batch-based.
- Dispensing uses FEFO — First Expiry, First Out — by default.
- Expired stock cannot be dispensed.
- Stock cannot become negative.
- Every inventory change must create a stock ledger record.
- Posted stock transactions are reversed, never silently deleted or overwritten.
- All inventory-changing database operations must be atomic database transactions.
- Authentication must use `password_hash()` / `password_verify()`.
- Protected actions require server-side role checks and CSRF protection.
- SQL access must use PDO prepared statements.
- Production secrets must not be committed to source control.
- Purchase request / indent / PO workflow is explicitly deferred from V1.

---

## Planned File Structure

```text
/
├── app/
│   ├── Auth/
│   │   ├── AuthService.php
│   │   └── Authorization.php
│   ├── Database/
│   │   └── Connection.php
│   ├── Employees/
│   │   ├── EmployeeRepository.php
│   │   └── EmployeeImportService.php
│   ├── Patients/
│   │   └── ExternalPatientRepository.php
│   ├── Doctors/
│   │   └── DoctorRepository.php
│   ├── Medicines/
│   │   └── MedicineRepository.php
│   ├── Suppliers/
│   │   └── SupplierRepository.php
│   ├── Inventory/
│   │   ├── BatchRepository.php
│   │   ├── StockLedgerService.php
│   │   ├── ReceiptService.php
│   │   ├── FefoAllocator.php
│   │   └── AdjustmentService.php
│   ├── Prescriptions/
│   │   └── PrescriptionService.php
│   ├── Dispensing/
│   │   └── DispenseService.php
│   ├── Reports/
│   │   └── ReportRepository.php
│   ├── Audit/
│   │   └── AuditLogger.php
│   └── Support/
│       ├── Csrf.php
│       ├── Flash.php
│       └── Validator.php
├── config/
│   ├── app.example.php
│   └── bootstrap.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── employees/
│   ├── patients/
│   ├── doctors/
│   ├── medicines/
│   ├── suppliers/
│   ├── receipts/
│   ├── prescriptions/
│   ├── dispensing/
│   ├── inventory/
│   ├── reports/
│   ├── users/
│   ├── settings/
│   ├── api/
│   └── assets/
├── templates/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── alerts.php
├── tests/
│   ├── bootstrap.php
│   ├── Auth/
│   ├── Employees/
│   ├── Inventory/
│   ├── Prescriptions/
│   ├── Dispensing/
│   └── Reports/
├── storage/
│   ├── logs/.gitkeep
│   └── backups/.gitkeep
├── composer.json
├── .gitignore
└── README.md
```

---

### Task 1: Bootstrap the Application, Database Connection, and Test Harness

**Files:**
- Create: `composer.json`
- Create: `.gitignore`
- Create: `config/app.example.php`
- Create: `config/bootstrap.php`
- Create: `app/Database/Connection.php`
- Create: `app/Support/Validator.php`
- Create: `tests/bootstrap.php`
- Create: `tests/Database/ConnectionTest.php`
- Create: `storage/logs/.gitkeep`
- Create: `storage/backups/.gitkeep`

**Interfaces:**
- Produces: `Connection::pdo(): PDO`
- Produces: `Validator::positiveInt(mixed $value): int`
- Produces: `Validator::requiredString(mixed $value, int $maxLength = 255): string`

- [ ] **Step 1: Add Composer metadata and PHPUnit dependency**

```json
{
  "name": "wfd-deeptech-labs/pharmacy-management",
  "description": "Estate Pharmacy Management System",
  "type": "project",
  "require": {
    "php": ">=8.1",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "Pharmacy\\": "app/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit"
  }
}
```

- [ ] **Step 2: Add local-secret exclusions**

```gitignore
/vendor/
/config/app.php
/.phpunit.cache/
/storage/logs/*.log
/storage/backups/*.sql
!.gitkeep
```

- [ ] **Step 3: Write the failing database configuration test**

```php
<?php
namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Pharmacy\Database\Connection;

final class ConnectionTest extends TestCase
{
    public function test_missing_database_configuration_throws_clear_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database configuration is missing');
        Connection::fromConfig([]);
    }
}
```

- [ ] **Step 4: Run the test to verify failure**

Run: `composer install && vendor/bin/phpunit tests/Database/ConnectionTest.php`

Expected: FAIL because `Connection` does not exist.

- [ ] **Step 5: Implement the PDO connection factory**

```php
<?php
namespace Pharmacy\Database;

use PDO;
use RuntimeException;

final class Connection
{
    public static function fromConfig(array $config): PDO
    {
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException('Database configuration is missing');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
```

- [ ] **Step 6: Add `config/app.example.php`**

```php
<?php
return [
    'app' => [
        'name' => 'Estate Pharmacy Management System',
        'developer' => 'WFd DeepTech Labs Private Limited',
        'timezone' => 'Asia/Kolkata',
        'session_timeout_minutes' => 30,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'pharmacy_management',
        'username' => 'pharmacy_user',
        'password' => 'change-me',
    ],
];
```

- [ ] **Step 7: Add shared validation helpers and their tests**

```php
<?php
namespace Pharmacy\Support;

use InvalidArgumentException;

final class Validator
{
    public static function positiveInt(mixed $value): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false || $filtered <= 0) {
            throw new InvalidArgumentException('Value must be a positive integer');
        }
        return $filtered;
    }

    public static function requiredString(mixed $value, int $maxLength = 255): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException('Required text is invalid');
        }
        return $value;
    }
}
```

- [ ] **Step 8: Run the unit tests**

Run: `vendor/bin/phpunit`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add composer.json .gitignore config app tests storage
git commit -m "chore: bootstrap pharmacy application"
```

---

### Task 2: Create the Relational Schema and Seed Core Roles

**Files:**
- Create: `database/schema.sql`
- Create: `database/seed.sql`
- Create: `tests/Database/SchemaTest.php`

**Interfaces:**
- Produces all core tables and constraints used by later services.
- Produces role names: `ADMIN`, `PHARMACIST`, `INVENTORY`, `MANAGEMENT_VIEWER`.

- [ ] **Step 1: Write a schema smoke test that verifies critical tables and constraints**

```php
<?php
namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function test_schema_declares_critical_inventory_tables(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        self::assertStringContainsString('CREATE TABLE medicine_batches', $sql);
        self::assertStringContainsString('CREATE TABLE stock_transactions', $sql);
        self::assertStringContainsString('CREATE TABLE dispense_items', $sql);
        self::assertStringContainsString('CHECK (quantity_available >= 0)', $sql);
    }
}
```

- [ ] **Step 2: Run the schema test to verify failure**

Run: `vendor/bin/phpunit tests/Database/SchemaTest.php`

Expected: FAIL because `schema.sql` does not exist.

- [ ] **Step 3: Create the exact V1 schema**

`database/schema.sql` must create these tables with InnoDB, utf8mb4, foreign keys, and timestamps:

```text
roles(id, code UNIQUE, name, created_at)
users(id, username UNIQUE, full_name, password_hash, active, last_login_at, created_at, updated_at)
user_roles(user_id, role_id, PRIMARY KEY(user_id, role_id))
employees(id, employee_code UNIQUE, name, division, department, designation, phone, employment_status, active, created_at, updated_at)
external_patients(id, patient_code UNIQUE, name, date_of_birth NULL, age NULL, gender NULL, phone NULL, address NULL, notes NULL, active, created_at, updated_at)
doctors(id, name, specialization NULL, registration_no NULL, phone NULL, visiting_schedule NULL, active, created_at, updated_at)
medicine_categories(id, name UNIQUE, active, created_at, updated_at)
medicines(id, medicine_code UNIQUE, name, generic_name NULL, strength NULL, dosage_form NULL, manufacturer NULL, category_id NULL, unit_of_measure, barcode NULL UNIQUE, hsn_code NULL, gst_rate DECIMAL(5,2) NULL, reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0, active, created_at, updated_at)
suppliers(id, supplier_code UNIQUE, name, address NULL, phone NULL, email NULL, gst_no NULL, contact_person NULL, active, created_at, updated_at)
stock_receipts(id, receipt_no UNIQUE, supplier_id, supplier_invoice_no, invoice_date, receipt_date, status ENUM('DRAFT','POSTED','REVERSED'), remarks NULL, created_by, posted_by NULL, posted_at NULL, created_at, updated_at)
stock_receipt_items(id, receipt_id, medicine_id, batch_no, manufacture_date NULL, expiry_date, received_quantity DECIMAL(12,3), free_quantity DECIMAL(12,3) DEFAULT 0, purchase_rate DECIMAL(12,4), tax_rate DECIMAL(5,2) DEFAULT 0, line_value DECIMAL(14,2), created_at)
medicine_batches(id, medicine_id, batch_no, manufacture_date NULL, expiry_date, purchase_rate DECIMAL(12,4), mrp DECIMAL(12,2) NULL, supplier_id NULL, receipt_item_id NULL, quantity_received DECIMAL(12,3), quantity_available DECIMAL(12,3), status ENUM('ACTIVE','EXPIRED','DEPLETED','BLOCKED'), created_at, updated_at, UNIQUE(medicine_id,batch_no,expiry_date), CHECK(quantity_available >= 0))
prescriptions(id, prescription_no UNIQUE, patient_type ENUM('EMPLOYEE','EXTERNAL'), employee_id NULL, external_patient_id NULL, doctor_id, visit_date, diagnosis_notes NULL, prescription_notes NULL, status ENUM('OPEN','PARTIALLY_DISPENSED','DISPENSED','CANCELLED'), entered_by, created_at, updated_at)
prescription_items(id, prescription_id, medicine_id, dosage_instruction NULL, frequency NULL, duration NULL, quantity_prescribed DECIMAL(12,3), quantity_dispensed DECIMAL(12,3) DEFAULT 0, remarks NULL, created_at)
dispenses(id, dispense_no UNIQUE, prescription_id NULL, patient_type ENUM('EMPLOYEE','EXTERNAL'), employee_id NULL, external_patient_id NULL, dispense_at, status ENUM('POSTED','REVERSED'), pharmacist_id, remarks NULL, reversed_by NULL, reversed_at NULL, created_at)
dispense_items(id, dispense_id, medicine_id, batch_id, prescription_item_id NULL, quantity DECIMAL(12,3), unit_cost DECIMAL(12,4), total_cost DECIMAL(14,2), dosage_instruction NULL, created_at)
stock_transactions(id, transaction_type ENUM('RECEIPT','DISPENSE','PATIENT_RETURN','SUPPLIER_RETURN','DAMAGE','EXPIRED','ADJUSTMENT_IN','ADJUSTMENT_OUT','OPENING_BALANCE','REVERSAL'), medicine_id, batch_id, quantity_in DECIMAL(12,3) DEFAULT 0, quantity_out DECIMAL(12,3) DEFAULT 0, unit_cost DECIMAL(12,4), transaction_value DECIMAL(14,2), source_type, source_id, reason NULL, user_id, created_at, CHECK(quantity_in >= 0), CHECK(quantity_out >= 0))
stock_adjustments(id, adjustment_no UNIQUE, adjustment_type ENUM('DAMAGE','EXPIRED','SUPPLIER_RETURN','ADJUSTMENT_IN','ADJUSTMENT_OUT'), batch_id, quantity DECIMAL(12,3), reason, status ENUM('POSTED','REVERSED'), created_by, created_at, reversed_by NULL, reversed_at NULL)
audit_logs(id, user_id NULL, action, module, record_type, record_id NULL, old_values JSON NULL, new_values JSON NULL, ip_address NULL, created_at)
system_settings(id, setting_key UNIQUE, setting_value TEXT, updated_by NULL, updated_at)
login_attempts(id, username, ip_address, successful, attempted_at)
```

Patient check constraints must enforce exactly one patient FK according to `patient_type` on prescriptions and dispenses.

- [ ] **Step 4: Add indexes for the hot paths**

Create indexes at minimum on:

```text
medicine_batches(medicine_id, expiry_date, quantity_available, status)
stock_transactions(medicine_id, batch_id, created_at)
dispenses(dispense_at, patient_type)
dispenses(employee_id, dispense_at)
dispenses(external_patient_id, dispense_at)
prescriptions(doctor_id, visit_date)
stock_receipts(receipt_date, supplier_id)
employees(employee_code, active)
```

- [ ] **Step 5: Seed roles and baseline settings**

`database/seed.sql` must insert the four role codes and settings:

```text
estate_name = Estate Pharmacy
near_expiry_days_1 = 30
near_expiry_days_2 = 60
near_expiry_days_3 = 90
session_timeout_minutes = 30
```

- [ ] **Step 6: Run the tests and load the schema into a local test DB**

Run:

```bash
vendor/bin/phpunit tests/Database/SchemaTest.php
mysql -u root -p pharmacy_management_test < database/schema.sql
mysql -u root -p pharmacy_management_test < database/seed.sql
```

Expected: tests PASS and SQL imports without errors.

- [ ] **Step 7: Commit**

```bash
git add database tests/Database/SchemaTest.php
git commit -m "feat: add pharmacy database schema"
```

---

### Task 3: Authentication, Role Authorization, CSRF, and Audit Logging

**Files:**
- Create: `app/Auth/AuthService.php`
- Create: `app/Auth/Authorization.php`
- Create: `app/Support/Csrf.php`
- Create: `app/Audit/AuditLogger.php`
- Create: `public/login.php`
- Create: `public/logout.php`
- Create: `public/index.php`
- Create: `tests/Auth/AuthServiceTest.php`
- Create: `tests/Auth/AuthorizationTest.php`

**Interfaces:**
- Produces: `AuthService::attempt(string $username, string $password, string $ip): bool`
- Produces: `AuthService::user(): ?array`
- Produces: `AuthService::logout(): void`
- Produces: `Authorization::requireRole(string ...$roleCodes): void`
- Produces: `Csrf::token(): string`
- Produces: `Csrf::validate(string $token): void`
- Produces: `AuditLogger::log(?int $userId, string $action, string $module, string $recordType, ?int $recordId, ?array $oldValues, ?array $newValues, ?string $ip): void`

- [ ] **Step 1: Write failing authentication tests**

Test cases must cover successful password verification, inactive users, incorrect passwords, and role loading.

- [ ] **Step 2: Run tests and confirm failure**

Run: `vendor/bin/phpunit tests/Auth`

Expected: FAIL because services do not exist.

- [ ] **Step 3: Implement session-based authentication**

Use `password_verify()`, regenerate session ID after login, update `last_login_at`, write `login_attempts`, and place only required identity fields in `$_SESSION['user']`.

- [ ] **Step 4: Implement role authorization**

`Authorization::requireRole()` must return normally when the current session has any allowed role; otherwise it must send HTTP 403 and stop execution.

- [ ] **Step 5: Implement CSRF tokens**

Generate a 32-byte random token per session and validate with `hash_equals()`.

- [ ] **Step 6: Implement append-only audit logging**

All writes use an `INSERT` into `audit_logs`; no update/delete method is exposed.

- [ ] **Step 7: Build Bootstrap login/logout pages**

Login form fields: username, password, hidden CSRF token. Failed login returns a generic message without revealing whether the username exists.

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit tests/Auth`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Auth app/Support/Csrf.php app/Audit public/login.php public/logout.php public/index.php tests/Auth
git commit -m "feat: add authentication and role authorization"
```

---

### Task 4: Build Employee, External Patient, Doctor, Medicine, and Supplier Masters

**Files:**
- Create repository classes under `app/Employees`, `app/Patients`, `app/Doctors`, `app/Medicines`, `app/Suppliers`
- Create CRUD pages under matching `public/` directories
- Create: `app/Employees/EmployeeImportService.php`
- Create: `tests/Employees/EmployeeImportServiceTest.php`
- Create: `tests/Medicines/MedicineRepositoryTest.php`

**Interfaces:**
- Produces: `EmployeeRepository::findByCode(string $employeeCode): ?array`
- Produces: `EmployeeImportService::importCsv(string $path, int $userId): array{inserted:int,updated:int,rejected:int,errors:array}`
- Produces: `ExternalPatientRepository::find(int $id): ?array`
- Produces: `DoctorRepository::active(): array`
- Produces: `MedicineRepository::searchActive(string $query, int $limit = 20): array`
- Produces: `SupplierRepository::active(): array`

- [ ] **Step 1: Write failing employee import tests**

Use an in-memory fixture CSV with columns:

```csv
employee_code,name,division,department,designation,phone,employment_status,active
1827,Selin Mary,Chulika,Field,Worker,9999999999,ACTIVE,1
```

Tests must verify insert, update by employee code, duplicate rows inside one file are rejected, and malformed rows are reported.

- [ ] **Step 2: Run the tests and verify failure**

Run: `vendor/bin/phpunit tests/Employees`

Expected: FAIL.

- [ ] **Step 3: Implement repositories using PDO prepared statements**

Repositories must expose only explicit create/update/find/search methods and must not expose raw SQL execution to UI pages.

- [ ] **Step 4: Implement CSV import**

Normalize `employee_code` as trimmed text so leading zeros are preserved. Wrap each imported row in validation; use an upsert transaction keyed by `employee_code`.

- [ ] **Step 5: Build role-protected Bootstrap CRUD pages**

Permissions:

```text
ADMIN: full master CRUD
PHARMACIST: employee/patient/doctor read; external patient create/edit; medicine read
INVENTORY: medicine/supplier read and permitted master maintenance if configured
MANAGEMENT_VIEWER: read-only
```

- [ ] **Step 6: Add SweetAlert2 confirmations for deactivate/reactivate operations**

No master record referenced by transactions is hard-deleted; use `active` status.

- [ ] **Step 7: Run tests and manual CRUD checks**

Run: `vendor/bin/phpunit tests/Employees tests/Medicines`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Employees app/Patients app/Doctors app/Medicines app/Suppliers public/employees public/patients public/doctors public/medicines public/suppliers tests/Employees tests/Medicines
git commit -m "feat: add pharmacy master data workflows"
```

---

### Task 5: Implement Stock Receipt Posting and Batch Creation

**Files:**
- Create: `app/Inventory/BatchRepository.php`
- Create: `app/Inventory/StockLedgerService.php`
- Create: `app/Inventory/ReceiptService.php`
- Create receipt pages under `public/receipts/`
- Create: `tests/Inventory/ReceiptServiceTest.php`

**Interfaces:**
- Produces: `BatchRepository::findOrCreateForReceipt(array $item): int`
- Produces: `StockLedgerService::record(array $movement): int`
- Produces: `ReceiptService::post(int $receiptId, int $userId): void`

- [ ] **Step 1: Write failing receipt-posting tests**

Tests must prove that posting one receipt item:

```text
received_quantity = 100
free_quantity = 10
purchase_rate = 2.50
```

creates `quantity_received = 110`, `quantity_available = 110`, and a `RECEIPT` ledger row with `quantity_in = 110`.

Also test duplicate posting is rejected.

- [ ] **Step 2: Run failing tests**

Run: `vendor/bin/phpunit tests/Inventory/ReceiptServiceTest.php`

Expected: FAIL.

- [ ] **Step 3: Implement receipt posting as one database transaction**

Algorithm:

```text
BEGIN
SELECT receipt FOR UPDATE
reject unless status=DRAFT
for each receipt item:
  validate quantity > 0
  validate expiry_date >= receipt_date
  create/reuse exact medicine+batch+expiry batch identity
  increase batch quantity_received and quantity_available
  insert RECEIPT stock transaction
mark receipt POSTED with posted_by and posted_at
write audit record
COMMIT
```

Rollback on any exception.

- [ ] **Step 4: Build receipt UI**

Include supplier, supplier invoice, invoice date, receipt date, repeatable medicine rows, batch, manufacture date, expiry, received/free quantity, purchase rate, tax, and calculated line value.

- [ ] **Step 5: Add duplicate invoice warning**

Warn when same supplier + supplier invoice number already exists, but enforce server-side rejection only when the existing receipt is POSTED.

- [ ] **Step 6: Run unit/integration tests**

Run: `vendor/bin/phpunit tests/Inventory/ReceiptServiceTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Inventory public/receipts tests/Inventory/ReceiptServiceTest.php
git commit -m "feat: add batch stock receipt posting"
```

---

### Task 6: Implement FEFO Allocation and Safe Dispensing

**Files:**
- Create: `app/Inventory/FefoAllocator.php`
- Create: `app/Dispensing/DispenseService.php`
- Create pages under `public/dispensing/`
- Create AJAX search endpoints under `public/api/`
- Create: `tests/Inventory/FefoAllocatorTest.php`
- Create: `tests/Dispensing/DispenseServiceTest.php`

**Interfaces:**
- Produces: `FefoAllocator::allocate(int $medicineId, float $quantity, string $asOfDate): array`
- Allocation row: `array{batch_id:int,quantity:float,expiry_date:string,unit_cost:float}`
- Produces: `DispenseService::post(array $command, int $pharmacistId): int`

- [ ] **Step 1: Write FEFO tests**

Given valid batches:

```text
Batch A: expiry 2026-12-31, available 5
Batch B: expiry 2027-01-31, available 10
Batch C: expiry 2026-09-01, available 100 (expired as of 2026-09-12)
```

Request 8 units as of `2026-09-12` must allocate:

```text
A -> 5
B -> 3
```

and never allocate C.

- [ ] **Step 2: Add insufficient-stock test**

Request 20 units must throw `DomainException('Insufficient valid stock')` and create no ledger rows.

- [ ] **Step 3: Run tests and verify failure**

Run: `vendor/bin/phpunit tests/Inventory/FefoAllocatorTest.php tests/Dispensing/DispenseServiceTest.php`

Expected: FAIL.

- [ ] **Step 4: Implement FEFO query using row locks**

Use:

```sql
SELECT id, expiry_date, quantity_available, purchase_rate
FROM medicine_batches
WHERE medicine_id = :medicine_id
  AND quantity_available > 0
  AND expiry_date >= :as_of_date
  AND status = 'ACTIVE'
ORDER BY expiry_date ASC, id ASC
FOR UPDATE;
```

- [ ] **Step 5: Implement dispense posting as one transaction**

Command shape:

```php
[
    'patient_type' => 'EMPLOYEE',
    'employee_id' => 123,
    'external_patient_id' => null,
    'prescription_id' => null,
    'remarks' => 'Direct issue',
    'items' => [
        ['medicine_id' => 7, 'quantity' => 8, 'dosage_instruction' => '1-0-1']
    ]
]
```

For each allocated batch: decrement `quantity_available`, insert `dispense_items`, and insert `DISPENSE` ledger row. If batch reaches zero, mark `DEPLETED`.

- [ ] **Step 6: Build the fast dispensing screen**

Screen must support employee-code lookup or external-patient search, medicine autocomplete, available valid stock display, requested quantity, dosage instruction, FEFO allocation preview, and final SweetAlert confirmation.

- [ ] **Step 7: Add server-side safeguards**

Reject inactive employees, inactive external patients, inactive medicines, zero/negative quantities, expired batches, and unauthorized pharmacists.

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit tests/Inventory/FefoAllocatorTest.php tests/Dispensing/DispenseServiceTest.php`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Inventory/FefoAllocator.php app/Dispensing public/dispensing public/api tests/Inventory/FefoAllocatorTest.php tests/Dispensing
git commit -m "feat: add FEFO pharmacy dispensing"
```

---

### Task 7: Add Visiting-Doctor Prescriptions and Prescription-Based Dispensing

**Files:**
- Create: `app/Prescriptions/PrescriptionService.php`
- Create pages under `public/prescriptions/`
- Modify: `app/Dispensing/DispenseService.php`
- Create: `tests/Prescriptions/PrescriptionServiceTest.php`
- Extend: `tests/Dispensing/DispenseServiceTest.php`

**Interfaces:**
- Produces: `PrescriptionService::create(array $command, int $userId): int`
- Produces: `PrescriptionService::remainingQuantity(int $prescriptionItemId): float`
- `DispenseService::post()` accepts optional `prescription_id` and `prescription_item_id`.

- [ ] **Step 1: Write failing prescription tests**

Create a prescription for an employee with doctor, visit date, diagnosis notes, and two medicine items. Verify initial item `quantity_dispensed = 0` and prescription status `OPEN`.

- [ ] **Step 2: Add partial dispensing tests**

Prescribed 10, dispense 6 -> item `quantity_dispensed=6`, prescription `PARTIALLY_DISPENSED`.

Dispense remaining 4 -> item `quantity_dispensed=10`, prescription `DISPENSED`.

Attempt to dispense 5 more -> reject.

- [ ] **Step 3: Run tests and verify failure**

Run: `vendor/bin/phpunit tests/Prescriptions tests/Dispensing`

Expected: FAIL.

- [ ] **Step 4: Implement prescription creation and validation**

Validate patient identity, active doctor, active medicines, positive prescribed quantities, and visit date.

- [ ] **Step 5: Extend dispensing transaction to update prescription quantities atomically**

The prescription item update must occur in the same transaction as batch decrement and ledger creation.

- [ ] **Step 6: Build prescription entry UI for pharmacy staff**

Fields: employee/external patient, visiting doctor, visit date, diagnosis/complaint notes, medicine, dosage, frequency, duration, quantity prescribed, remarks.

- [ ] **Step 7: Build “Dispense Prescription” action**

Show prescribed, already dispensed, remaining, valid stock, and FEFO allocation before posting.

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit tests/Prescriptions tests/Dispensing`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Prescriptions app/Dispensing public/prescriptions public/dispensing tests/Prescriptions tests/Dispensing
git commit -m "feat: add visiting doctor prescriptions"
```

---

### Task 8: Implement Stock Adjustments, Expiry, Damage, Returns, and Reversals

**Files:**
- Create: `app/Inventory/AdjustmentService.php`
- Create pages under `public/inventory/adjustments/`
- Create: `tests/Inventory/AdjustmentServiceTest.php`
- Modify: `app/Dispensing/DispenseService.php`
- Modify: `app/Inventory/ReceiptService.php`

**Interfaces:**
- Produces: `AdjustmentService::post(string $type, int $batchId, float $quantity, string $reason, int $userId): int`
- Produces: `AdjustmentService::reverse(int $adjustmentId, string $reason, int $userId): void`
- Produces: `DispenseService::reverse(int $dispenseId, string $reason, int $userId): void`

- [ ] **Step 1: Write failing adjustment tests**

Tests must cover DAMAGE, EXPIRED, SUPPLIER_RETURN, ADJUSTMENT_IN, ADJUSTMENT_OUT, insufficient stock rejection, and reversal restoring prior quantity.

- [ ] **Step 2: Run tests and verify failure**

Run: `vendor/bin/phpunit tests/Inventory/AdjustmentServiceTest.php`

Expected: FAIL.

- [ ] **Step 3: Implement controlled adjustment posting**

For stock-out types, lock the batch, verify available quantity, decrement, and insert matching `stock_transactions` row. For adjustment-in, increment and ledger it. Reason is mandatory.

- [ ] **Step 4: Implement dispense reversal**

A reversal must not delete the original dispense. It creates balancing `REVERSAL` stock transactions, restores batch quantities, updates the dispense status to `REVERSED`, and restores prescription dispensed quantities where applicable.

- [ ] **Step 5: Add expiry-status maintenance**

On dashboard/inventory access, batches with `expiry_date < CURRENT_DATE` and positive stock are presented as expired and unavailable. A maintenance command/page may mark status `EXPIRED`, but eligibility for dispensing must always use the date predicate, not status alone.

- [ ] **Step 6: Build adjustment and write-off screens**

Require batch, type, quantity, reason, and confirmation. Show before/after quantity in the confirmation dialog.

- [ ] **Step 7: Run tests**

Run: `vendor/bin/phpunit tests/Inventory/AdjustmentServiceTest.php tests/Dispensing`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Inventory app/Dispensing public/inventory tests/Inventory tests/Dispensing
git commit -m "feat: add controlled stock adjustments and reversals"
```

---

### Task 9: Build Dashboard, Operational Reports, and Cost/Value Reports

**Files:**
- Create: `app/Reports/ReportRepository.php`
- Create: `public/dashboard.php`
- Create report pages under `public/reports/`
- Create: `tests/Reports/ReportRepositoryTest.php`

**Interfaces:**
- Produces report methods returning arrays only; formatting stays in views.
- Required methods:

```php
ReportRepository::dashboard(string $date): array
ReportRepository::currentStock(array $filters = []): array
ReportRepository::stockLedger(array $filters = []): array
ReportRepository::dispensing(array $filters = []): array
ReportRepository::consumptionByMedicine(array $filters = []): array
ReportRepository::consumptionByDivision(array $filters = []): array
ReportRepository::patientHistory(string $type, int $patientId): array
ReportRepository::expiry(int $days): array
ReportRepository::inventoryValue(): float
ReportRepository::consumptionValue(array $filters = []): array
ReportRepository::supplierPurchases(array $filters = []): array
```

- [ ] **Step 1: Write report calculation tests with fixed fixtures**

Fixture example:

```text
Receipt: 100 tablets at 2.00 = 200.00
Dispense: 10 tablets at acquisition snapshot 2.00 = 20.00
Damage: 5 tablets at 2.00 = 10.00
Current usable qty: 85
Current inventory value: 170.00
```

Tests must assert these exact values.

- [ ] **Step 2: Run tests and verify failure**

Run: `vendor/bin/phpunit tests/Reports`

Expected: FAIL.

- [ ] **Step 3: Implement report queries using ledger and snapshot costs**

Do not calculate historical dispense cost from current batch price. Historical cost comes from `dispense_items.unit_cost` / `stock_transactions.unit_cost` snapshots.

- [ ] **Step 4: Build dashboard cards**

Display: dispenses today, patients served today, quantity dispensed today, consumption value today, current inventory value, low-stock count, near-expiry count, expired-batch count.

- [ ] **Step 5: Build operational report pages**

Include daily/date-range dispensing, employee history, external-patient history, medicine consumption, division/department consumption, doctor prescriptions, current stock, batch stock, ledger, low stock, near expiry, expired stock, damaged stock, adjustment report, receipt report, and supplier receipt report.

- [ ] **Step 6: Build management cost reports**

Include current inventory value, monthly consumption value, employee cost, external-patient cost, division/department cost, medicine consumption value, supplier purchase value, expiry/damage/adjustment value, and receipt-vs-consumption trend.

- [ ] **Step 7: Add CSV export**

Each tabular report gets a server-side CSV export endpoint that repeats the same filters and authorization checks.

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit tests/Reports`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Reports public/dashboard.php public/reports tests/Reports
git commit -m "feat: add pharmacy dashboards and reports"
```

---

### Task 10: Build the Shared Bootstrap UI, Navigation, and Role-Specific Experience

**Files:**
- Create: `templates/header.php`
- Create: `templates/sidebar.php`
- Create: `templates/footer.php`
- Create: `templates/alerts.php`
- Create: `public/assets/css/app.css`
- Create: `public/assets/js/app.js`
- Modify all public pages to use shared templates.

**Interfaces:**
- Navigation visibility depends on session roles.
- Server authorization remains mandatory even when links are hidden.

- [ ] **Step 1: Build the WFd DeepTech Labs branded shell**

Header/footer copy must include:

```text
Estate Pharmacy Management System
Developed by WFd DeepTech Labs Private Limited
```

The estate/company name is read from `system_settings.estate_name`.

- [ ] **Step 2: Create role-specific navigation**

```text
ADMIN: Dashboard, Masters, Receipts, Prescriptions, Dispensing, Inventory, Reports, Users, Settings, Audit
PHARMACIST: Dashboard, Employees, External Patients, Doctors, Prescriptions, Dispensing, Patient History, Stock Lookup
INVENTORY: Dashboard, Medicines, Suppliers, Receipts, Inventory, Adjustments, Expiry, Reports
MANAGEMENT_VIEWER: Dashboard, Reports only
```

- [ ] **Step 3: Add SweetAlert2 helpers**

Create one shared JS function for confirmation dialogs and one for success/error flash display. Do not duplicate alert configuration on every page.

- [ ] **Step 4: Initialize DataTables consistently**

Use one shared initializer with search, pagination, sensible page length, and local assets suitable for offline use.

- [ ] **Step 5: Verify offline behavior**

No runtime CSS/JS dependency may rely on a public CDN. Bootstrap, DataTables, and SweetAlert assets must be stored under `public/assets/vendor/` or installed locally and copied during deployment.

- [ ] **Step 6: Manual role walkthrough**

Login as each role and verify visible navigation and blocked URLs match the permission model.

- [ ] **Step 7: Commit**

```bash
git add templates public/assets public
git commit -m "feat: add role-based pharmacy user interface"
```

---

### Task 11: Add User Administration, Settings, Audit Viewer, Backup, and Restore Documentation

**Files:**
- Create pages under `public/users/`
- Create pages under `public/settings/`
- Create: `public/reports/audit-log.php`
- Create: `scripts/backup.php`
- Create: `README.md`
- Create: `docs/DEPLOYMENT.md`
- Create: `docs/BACKUP_RESTORE.md`
- Create: `tests/Auth/PasswordPolicyTest.php`

**Interfaces:**
- Admin can create/deactivate users and assign one or more roles.
- Admin can change settings and trigger a manual DB backup.
- Audit log is read-only.

- [ ] **Step 1: Write password-policy tests**

Minimum V1 policy: at least 10 characters and reject blank/whitespace-only passwords. Password hashes use PHP default algorithm.

- [ ] **Step 2: Implement user administration**

Never display stored hashes. Reset password by replacing the hash with `password_hash($newPassword, PASSWORD_DEFAULT)`.

- [ ] **Step 3: Implement settings administration**

Editable settings: estate name, near-expiry thresholds (30/60/90 defaults), session timeout. Every change writes an audit log.

- [ ] **Step 4: Build read-only audit viewer**

Filters: date range, user, module, action, record type.

- [ ] **Step 5: Implement backup script**

`scripts/backup.php` reads DB config, writes a timestamped `.sql` backup into `storage/backups/`, and returns non-zero on failure. Production scheduling documentation uses Windows Task Scheduler or Linux cron depending on host OS.

- [ ] **Step 6: Write deployment documentation**

Document:

```text
1. PHP/Apache/MySQL prerequisites
2. Clone/copy repository
3. composer install --no-dev for production
4. Copy config/app.example.php to config/app.php
5. Create DB and user
6. Import database/schema.sql then database/seed.sql
7. Create initial ADMIN password hash through a one-time CLI command or documented SQL helper
8. Point Apache document root to public/
9. Set writable permissions for storage/logs and storage/backups
10. Access from estate LAN using server local IP
```

- [ ] **Step 7: Write backup/restore documentation**

Document both manual backup and restore into a clean database. Require verification on a non-production database before replacing production data.

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add public/users public/settings public/reports/audit-log.php scripts README.md docs tests/Auth/PasswordPolicyTest.php
git commit -m "feat: add administration backup and deployment docs"
```

---

### Task 12: End-to-End Acceptance, Security Review, and V1 Release

**Files:**
- Create: `tests/Acceptance/V1WorkflowTest.php`
- Create: `docs/ACCEPTANCE_TESTS.md`
- Create: `CHANGELOG.md`
- Modify files discovered during review only when needed to satisfy the checks below.

**Interfaces:**
- No new feature interfaces; this task verifies the full V1 contract.

- [ ] **Step 1: Write the end-to-end workflow test**

The automated scenario must execute:

```text
create employee
create doctor
create medicine
create supplier
create + post receipt with two batches
create visiting-doctor prescription
dispense quantity spanning FEFO batches
assert expired batch cannot be used
assert stock balances
assert stock ledger rows
assert prescription remaining quantity
assert employee history cost
post damaged-stock adjustment
reverse a dispense
assert final balances and audit entries
```

- [ ] **Step 2: Run the complete test suite**

Run: `vendor/bin/phpunit`

Expected: all tests PASS.

- [ ] **Step 3: Run PHP syntax validation**

Run:

```bash
find app config public templates scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 4: Perform security checks**

Verify manually and document results:

```text
prepared statements on all request-derived SQL values
CSRF on every state-changing POST
role check on every protected route/API endpoint
HTML output escaped with htmlspecialchars
session regeneration on login
logout destroys session
no default/admin plaintext password in repo
no config/app.php committed
no stock-changing GET endpoint
no direct quantity edit UI
expired stock rejected server-side
negative stock impossible under concurrent transaction locking
```

- [ ] **Step 5: Perform localhost/LAN acceptance test**

Run the application on the target local host, connect from at least one second machine on the LAN, and complete one realistic receipt + prescription + dispense + report workflow.

- [ ] **Step 6: Write `docs/ACCEPTANCE_TESTS.md` with pass/fail evidence**

Include tested browser, PHP version, DB version, server OS, client OS, role scenarios, workflow scenarios, backup/restore result, and known limitations.

- [ ] **Step 7: Create V1 changelog**

```markdown
# Changelog

## 1.0.0 - 2026

- Estate employee and external-patient pharmacy workflows
- Visiting-doctor prescription capture
- Batch-wise FEFO inventory
- Stock receipts and controlled adjustments
- Expiry, damage, and low-stock monitoring
- Operational and cost/value reporting
- RBAC and audit logging
- Localhost/LAN deployment and backup support
```

- [ ] **Step 8: Final commit**

```bash
git add tests/Acceptance docs/ACCEPTANCE_TESTS.md CHANGELOG.md
git commit -m "test: complete pharmacy v1 acceptance coverage"
```

- [ ] **Step 9: Tag only after successful acceptance**

```bash
git tag -a v1.0.0 -m "Estate Pharmacy Management System v1.0.0"
git push origin main --tags
```

---

## Implementation Order and Review Gates

The tasks must be executed in order because later modules depend on earlier interfaces. A reviewer should gate each task before the next task begins. In particular, Tasks 5–8 are inventory-critical and must not be merged with failing tests or manual stock mutations.

Recommended checkpoints:

```text
Checkpoint A: Tasks 1–3 — foundation/security
Checkpoint B: Task 4 — master data
Checkpoint C: Tasks 5–8 — inventory/prescription/dispensing core
Checkpoint D: Tasks 9–11 — reporting/UI/administration
Checkpoint E: Task 12 — acceptance/release
```

## Definition of Done

V1 is complete only when all of the following are true:

- Employee and external patients can be identified quickly.
- Visiting-doctor prescriptions can be entered by pharmacy staff.
- Stock receipts create auditable batch stock.
- FEFO dispensing works across multiple batches.
- Expired stock is rejected server-side.
- Inventory never goes negative.
- Every stock movement has a stock ledger source.
- Reversals preserve the original transaction history.
- Low-stock and expiry alerts work.
- Operational and cost/value reports reconcile with ledger data.
- All four roles enforce correct permissions.
- Audit logs record sensitive actions.
- Backup and restore are documented and tested.
- The application works without internet access on localhost/LAN.
- The full PHPUnit suite passes.
- The acceptance workflow passes on the target LAN environment.
