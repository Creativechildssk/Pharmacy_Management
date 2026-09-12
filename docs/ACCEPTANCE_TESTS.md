# V1 Acceptance Checklist

## Automated checks

- [ ] PHPUnit suite passes on GitHub Actions
- [ ] PHP syntax validation passes for application PHP files
- [ ] Database schema imports cleanly into MySQL/MariaDB

## Security checks

- [x] Request-derived SQL values use prepared statements in application workflows
- [x] State-changing web forms use CSRF tokens
- [x] Protected screens enforce server-side roles
- [x] Passwords are hashed with PHP password APIs
- [x] Local production config is excluded by `.gitignore`
- [x] No supported stock-changing GET route
- [x] Expired batches are rejected by dispensing query and FEFO allocator
- [x] Stock-out operations lock relevant batch rows and reject insufficient quantity

## Target LAN acceptance to perform after deployment

- [ ] Login as Admin
- [ ] Create Pharmacist, Inventory and Management Viewer users
- [ ] Add/import employees
- [ ] Register an external patient
- [ ] Add visiting doctor
- [ ] Add medicines and supplier
- [ ] Post receipt with two batches of the same medicine
- [ ] Enter visiting-doctor prescription
- [ ] Dispense quantity spanning two batches and confirm earliest valid expiry is consumed first
- [ ] Confirm expired batch cannot be dispensed
- [ ] Post damage/expiry write-off
- [ ] Confirm stock ledger and inventory balances reconcile
- [ ] Confirm employee/division cost reports reconcile with dispense item cost snapshots
- [ ] Export a CSV report
- [ ] Run backup and restore it to a non-production test database
- [ ] Access application from a second computer on the estate LAN

## Release rule

Do not label a deployment as production-ready until the three automated checks and the target LAN acceptance checklist are completed on the actual estate server environment.
