# WFd DeepTech Labs — Estate Pharmacy Management System

**Project:** Pharmacy Management
**Repository:** `Creativechildssk/Pharmacy_Management`
**Developer / Product Owner:** WFd DeepTech Labs Private Limited
**Deployment:** Localhost / Estate LAN
**Date:** 12 September 2026
**Status:** Design approved in principle; implementation plan pending final review

## 1. Purpose

Build a simple, reliable estate pharmacy and dispensary management system for medicine inventory, visiting-doctor prescription capture, employee and external-patient dispensing, expiry control, stock accountability, and management reporting.

The system is intentionally not a retail pharmacy POS or a full hospital EMR. It focuses on estate dispensary operations and must remain easy to deploy, operate, maintain, audit, and extend.

## 2. Technology Stack

- PHP 8.x
- MySQL or MariaDB
- Apache
- Bootstrap 5
- SweetAlert2
- DataTables
- Vanilla JavaScript with AJAX where asynchronous interactions improve speed
- HTML/CSS

No internet connection is required for day-to-day operation. The application runs on one local server and is accessed by authorized users over the estate LAN.

## 3. Architectural Approach

Use a modular PHP monolith. The application is one deployable system with clear internal modules and shared authentication, authorization, database access, and audit logging.

Suggested module layout:

```text
/pharmacy
├── config/
├── includes/
├── modules/
│   ├── dashboard/
│   ├── employees/
│   ├── patients/
│   ├── doctors/
│   ├── medicines/
│   ├── suppliers/
│   ├── purchases/
│   ├── inventory/
│   ├── prescriptions/
│   ├── dispensing/
│   ├── adjustments/
│   ├── reports/
│   ├── users/
│   └── settings/
├── api/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── database/
├── tests/
├── login.php
└── index.php
```

## 4. User Roles

### 4.1 Admin

Full system access.

Responsibilities:
- Manage users and roles
- Manage master data
- Configure system settings
- View all reports
- Perform authorized corrections
- Manage audit-sensitive configuration

### 4.2 Pharmacist

Responsibilities:
- Search employees and external patients
- Register external patients
- Enter visiting-doctor prescriptions from handwritten prescriptions
- Dispense medicines
- View patient dispensing history
- View stock availability required for dispensing

The pharmacist must not directly alter stock quantities outside controlled stock transactions.

### 4.3 Store / Inventory User

Responsibilities:
- Record medicine receipts
- Manage supplier-related stock entries
- Manage batch information
- Record damaged, expired, returned, or adjusted stock
- Maintain reorder levels
- View inventory and stock reports

### 4.4 Management Viewer

Read-only access to dashboards and management reports.

No create, edit, delete, dispense, receipt, or adjustment rights.

## 5. Patient Model

The system supports two patient categories.

### 5.1 Employee

Fields include:
- Employee ID
- Employee name
- Division
- Department
- Designation
- Employment status
- Optional phone number

Employee ID must be unique.

Employees can be added manually or imported in bulk through CSV / Excel-compatible import files. Import must validate duplicate employee IDs and support active/inactive status updates.

### 5.2 External Patient

Fields include:
- System-generated patient ID
- Name
- Age or date of birth
- Gender
- Phone number
- Address
- Notes
- Active status

All dispensing records must identify whether the recipient is an employee or external patient.

## 6. Visiting Doctor Model

Visiting doctors do not require system accounts in Version 1.

Pharmacy staff records prescriptions received from visiting doctors.

Doctor master fields include:
- Doctor name
- Specialization
- Registration number
- Phone number
- Typical visiting day or schedule note
- Active status

## 7. Prescription Workflow

Main workflow:

```text
Employee / External Patient
        ↓
Visiting Doctor Consultation
        ↓
Handwritten Prescription
        ↓
Pharmacy Staff Prescription Entry
        ↓
Medicine Dispensing
        ↓
Batch Stock Deduction
        ↓
Patient History + Reports + Audit Trail
```

Prescription header fields:
- Prescription number
- Patient type
- Employee ID or external patient ID
- Visiting doctor
- Visit date
- Diagnosis / complaint / remarks
- Prescription notes
- Entered by
- Created timestamp

Prescription item fields:
- Medicine
- Dosage instruction
- Frequency
- Duration
- Quantity prescribed
- Quantity dispensed
- Remarks

A prescription may exist before dispensing, and the quantity actually dispensed may be lower than the quantity prescribed.

## 8. Medicine Master

Medicine master fields include:
- Medicine ID
- Medicine name
- Generic name
- Strength
- Dosage form
- Manufacturer
- Category
- Unit of measure
- Optional barcode
- HSN code if required
- GST rate if required for accounting reference
- Reorder level
- Active status

Medicine master must not contain a single editable stock quantity field. Stock exists at batch level and is derived from stock transactions.

## 9. Supplier Master

Fields include:
- Supplier ID
- Supplier name
- Address
- Phone
- Email
- GST number if applicable
- Contact person
- Active status

## 10. Medicine Receipt / Purchase Receipt

Version 1 begins when medicines physically arrive at the dispensary. Purchase requests, indents, approvals, and purchase orders are deferred to a future milestone.

Receipt header fields:
- Receipt number
- Supplier
- Supplier invoice number
- Invoice date
- Receipt date
- Entered by
- Remarks

Receipt item fields:
- Medicine
- Batch number
- Manufacture date
- Expiry date
- Received quantity
- Free quantity, if applicable
- Purchase rate
- Tax rate, if applicable
- Line value

Posting a receipt must create positive stock ledger entries and batch stock availability.

## 11. Batch Inventory

All medicine inventory is batch-based.

Batch fields include:
- Batch ID
- Medicine ID
- Batch number
- Manufacture date
- Expiry date
- Purchase rate
- MRP, if captured
- Supplier reference
- Receipt reference
- Quantity received
- Quantity available
- Batch status

The same medicine may have multiple active batches.

Expired batches must never be available for dispensing.

## 12. FEFO Dispensing

Dispensing follows **FEFO — First Expiry, First Out** by default.

When a medicine is selected, the system should:

1. Find all non-expired batches with positive available quantity.
2. Sort by nearest expiry date first.
3. Recommend allocation from the earliest-expiring valid batch.
4. Continue into the next valid batch if the required quantity exceeds the first batch balance.
5. Prevent quantities greater than available stock.
6. Prevent expired batch selection.

Manual batch override may be allowed only to authorized users and must be auditable.

## 13. Dispensing Transaction

Dispensing header fields:
- Dispense number
- Prescription reference, optional for direct dispensing
- Patient type
- Employee or external patient reference
- Dispense date/time
- Pharmacist user
- Remarks

Dispensing item fields:
- Medicine
- Batch
- Quantity
- Unit acquisition cost snapshot
- Total cost snapshot
- Dosage instruction if applicable

A completed dispense operation must atomically:

1. Validate patient.
2. Validate medicine and batch availability.
3. Validate expiry.
4. Create dispense records.
5. Create negative stock ledger entries.
6. Update batch availability.
7. Write an audit event.

If any step fails, the entire transaction must roll back.

## 14. Direct Dispensing

The system may support direct dispensing without a previously entered prescription for authorized pharmacist workflows such as routine or urgent issues.

Direct dispensing must still require:
- Patient identification
- Medicine and quantity
- Batch validation
- Pharmacist identity
- Reason or remarks when configured
- Stock ledger entry
- Audit trail

## 15. Stock Ledger

`stock_transactions` is the authoritative movement ledger.

Every inventory-changing action creates a ledger transaction.

Supported movement types include:
- RECEIPT
- DISPENSE
- CUSTOMER_RETURN / PATIENT_RETURN, if enabled
- SUPPLIER_RETURN
- DAMAGE
- EXPIRED
- ADJUSTMENT_IN
- ADJUSTMENT_OUT
- OPENING_BALANCE

Each record includes:
- Transaction ID
- Transaction type
- Medicine ID
- Batch ID
- Quantity in
- Quantity out
- Unit cost snapshot
- Transaction value
- Source document type
- Source document ID
- Reason
- User ID
- Timestamp

Direct SQL updates to stock balances from the user interface are prohibited.

## 16. Stock Adjustments

Stock corrections must be controlled transactions, never direct edits.

Required fields:
- Medicine
- Batch
- Adjustment type
- Quantity
- Reason
- User
- Timestamp

The system records the before and after balance in the audit log where practical.

## 17. Expiry and Damage Management

The inventory dashboard must distinguish:
- Valid stock
- Near-expiry stock
- Expired stock
- Damaged stock

Near-expiry thresholds should be configurable, with defaults for:
- 30 days
- 60 days
- 90 days

Expired batches are blocked from dispensing.

Removal of expired or damaged stock requires a formal stock transaction with reason and user identity.

## 18. Low Stock Management

Each medicine may have a reorder level.

Low-stock status is calculated using total valid, usable stock across non-expired batches.

Dashboard alerts must show medicines at or below reorder level.

## 19. Reporting

### 19.1 Operational Reports

- Daily dispensing report
- Date-range dispensing report
- Employee-wise medicine history
- External-patient medicine history
- Medicine-wise consumption
- Division-wise consumption
- Department-wise consumption
- Visiting-doctor prescription report
- Current stock
- Batch-wise stock
- Stock movement ledger
- Low-stock report
- Near-expiry report
- Expired-stock report
- Damaged-stock report
- Stock-adjustment report
- Medicine receipt report
- Supplier-wise receipt report

### 19.2 Cost and Management Reports

- Current inventory value
- Monthly medicine consumption value
- Employee-wise medicine cost
- External-patient medicine cost
- Division-wise medicine cost
- Department-wise medicine cost
- Medicine-wise consumption value
- Supplier-wise purchase value
- Expired-stock value
- Damaged-stock value
- Adjustment value
- Purchase / receipt vs consumption trend
- Monthly consumption summary

Reports must support practical filters such as date range, medicine, division, patient, supplier, and doctor where relevant.

## 20. Dashboard

Dashboard cards should include:
- Dispenses today
- Patients served today
- Items / quantities dispensed today
- Consumption value today
- Current inventory value
- Low-stock medicine count
- Near-expiry batch count
- Expired batch count

Dashboard sections may include:
- Monthly consumption trend
- Recent dispensing
- Low-stock list
- Near-expiry list
- Top consumed medicines
- Division-wise consumption summary

Role-based dashboards may hide operational actions from Management Viewer.

## 21. Audit Logging

Important actions must record:
- User
- Action
- Module
- Record type
- Record ID
- Timestamp
- Old value where appropriate
- New value where appropriate
- IP address / workstation identifier where available

Audit-sensitive actions include:
- Login / logout
- User changes
- Medicine master changes
- Employee / patient changes
- Receipt posting
- Dispensing
- Dispense cancellation
- Stock adjustments
- Expired / damaged stock write-off
- Settings changes

Audit records must not be editable through the normal application UI.

## 22. Authentication and Security

Minimum requirements:
- PHP session-based authentication
- `password_hash()` / `password_verify()` for passwords
- Role-based authorization on every protected action
- Prepared statements / PDO or MySQLi prepared queries
- CSRF protection on state-changing forms
- Output escaping against XSS
- Server-side validation regardless of client-side validation
- Session timeout configuration
- Login attempt throttling or basic lockout protection
- No plaintext credentials in source control
- Database configuration through local environment/config not committed with production secrets

## 23. User Interface

Use Bootstrap 5 for a clean responsive interface.

SweetAlert2 is used for:
- Success confirmations
- Delete / cancel confirmations
- Stock warnings
- Near-expiry warnings
- Validation feedback where appropriate

DataTables is used for searchable and pageable operational lists and reports.

The user interface must prioritize speed for pharmacy staff. Common workflows should require minimal clicks.

## 24. Error Handling

Database operations that change inventory must use transactions.

User-facing errors should be understandable and must not expose SQL, file paths, passwords, or stack traces.

Technical errors should be logged locally for troubleshooting.

Critical validation errors include:
- Unknown patient
- Inactive employee
- Missing medicine
- Insufficient stock
- Expired batch
- Invalid quantity
- Duplicate stock receipt posting
- Duplicate employee ID
- Unauthorized action

## 25. Data Integrity Rules

- Employee ID is unique.
- External patient system ID is unique.
- Medicine IDs are unique.
- Batch identity is tied to medicine and batch number.
- Stock cannot become negative.
- Expired stock cannot be dispensed.
- Every stock balance change must have a ledger source.
- Dispensing and stock updates occur in one database transaction.
- Posted stock receipts cannot silently be edited in ways that break the ledger.
- Cancellation is performed through reversal transactions rather than record deletion where stock is affected.

## 26. Backup and Local Deployment

The application is hosted locally on the estate network.

Deployment may begin with XAMPP / WAMP for development and testing, with a dedicated Apache + PHP + MariaDB host recommended for stable production use.

Backup requirements:
- Automated scheduled database backup
- Manual backup option for Admin
- Backup files stored outside the live database directory
- Clear restore procedure
- Optional secondary-device / NAS backup in a future enhancement

## 27. Branding

The login screen, footer, reports, and system information should identify:

**Developed by WFd DeepTech Labs Private Limited**

The estate or operating company name should be configurable through system settings rather than hard-coded.

## 28. Version 1 Scope

Version 1 includes:
- Authentication and RBAC
- Employee master
- Employee import
- External patient master
- Visiting doctor master
- Medicine master
- Supplier master
- Medicine receipt
- Batch inventory
- FEFO allocation
- Prescription entry
- Direct dispensing
- Prescription-based dispensing
- Stock ledger
- Stock adjustments
- Expiry and damage handling
- Low-stock alerts
- Operational reports
- Cost/value reports
- Dashboard
- Audit log
- Basic backup support
- Local LAN deployment documentation

## 29. Explicitly Deferred Features

Future milestones may include:
- Purchase request / indent workflow
- Approval chain
- Purchase orders
- Supplier payment tracking
- Multi-store / multi-dispensary inventory
- Barcode scanner optimization
- Label printing
- Mobile application
- SMS / WhatsApp notifications
- Doctor self-service login
- Full consultation / EMR module
- Laboratory module
- Integration with HR / ERP employee master
- Central server synchronization
- Advanced analytics and forecasting

These features must not complicate Version 1 unless they are required to preserve a clean extension point.

## 30. Core Database Entities

Expected core tables include:
- `users`
- `roles`
- `user_roles` or equivalent role mapping
- `employees`
- `external_patients`
- `doctors`
- `medicine_categories`
- `medicines`
- `suppliers`
- `stock_receipts`
- `stock_receipt_items`
- `medicine_batches`
- `prescriptions`
- `prescription_items`
- `dispenses`
- `dispense_items`
- `stock_transactions`
- `stock_adjustments`
- `audit_logs`
- `system_settings`

The implementation plan will define exact columns, foreign keys, indexes, constraints, and transaction boundaries.

## 31. Testing Strategy

Critical business rules require automated tests where practical.

Priority test areas:
- Authentication and authorization
- Employee uniqueness
- Receipt posting
- Batch creation
- FEFO batch ordering
- Expiry rejection
- Insufficient-stock rejection
- Multi-batch dispensing
- Stock ledger integrity
- Transaction rollback on failure
- Adjustment posting
- Report calculations
- Inventory valuation calculations

Manual acceptance testing must include realistic pharmacy workflows on the target localhost / LAN environment.

## 32. Definition of Success

Version 1 is successful when pharmacy staff can:

1. Find an employee or external patient quickly.
2. Record a visiting-doctor prescription.
3. Dispense medicines without selecting expired stock.
4. Automatically consume the earliest-expiring valid stock first.
5. See accurate stock after every transaction.
6. Trace every quantity change back to a user and source transaction.
7. Identify low and near-expiry stock immediately.
8. Produce reliable employee, patient, division, inventory, consumption, and cost reports.
9. Operate the system entirely on the estate LAN without internet dependence.
10. Back up and restore the database through a documented process.

## 33. Future Milestone: Procurement Workflow

After Version 1 is stable, procurement can extend the current receipt model with:

```text
Indent / Purchase Request
        ↓
Approval
        ↓
Purchase Order
        ↓
Supplier
        ↓
Medicine Receipt
        ↓
Existing Batch Inventory Workflow
```

This preserves Version 1 inventory architecture while adding upstream purchasing control later.
