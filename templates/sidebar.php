<?php
use Pharmacy\Auth\Authorization;
$canAdmin=Authorization::hasRole('ADMIN');
$canPharmacy=Authorization::hasRole('ADMIN','PHARMACIST');
$canInventory=Authorization::hasRole('ADMIN','INVENTORY');
$canReports=Authorization::hasRole('ADMIN','PHARMACIST','INVENTORY','MANAGEMENT_VIEWER');
?>
<aside class="sidebar bg-white border-end p-3">
  <div class="nav flex-column gap-1">
    <a class="nav-link" href="/dashboard.php">Dashboard</a>
    <?php if ($canPharmacy): ?>
      <a class="nav-link" href="/employees/index.php">Employees</a>
      <a class="nav-link" href="/patients/index.php">External Patients</a>
      <a class="nav-link" href="/doctors/index.php">Visiting Doctors</a>
      <a class="nav-link" href="/prescriptions/index.php">Prescriptions</a>
      <a class="nav-link fw-semibold" href="/dispensing/index.php">Dispense</a>
    <?php endif; ?>
    <?php if ($canInventory): ?>
      <a class="nav-link" href="/medicines/index.php">Medicines</a>
      <a class="nav-link" href="/suppliers/index.php">Suppliers</a>
      <a class="nav-link" href="/receipts/index.php">Stock Receipts</a>
      <a class="nav-link" href="/inventory/index.php">Inventory</a>
      <a class="nav-link" href="/inventory/adjustments.php">Adjustments</a>
    <?php endif; ?>
    <?php if ($canReports): ?><a class="nav-link" href="/reports/index.php">Reports</a><?php endif; ?>
    <?php if ($canAdmin): ?>
      <a class="nav-link" href="/users/index.php">Users</a>
      <a class="nav-link" href="/settings/index.php">Settings</a>
      <a class="nav-link" href="/reports/audit-log.php">Audit Log</a>
    <?php endif; ?>
    <hr><a class="nav-link text-danger" href="/logout.php">Logout</a>
  </div>
</aside>
