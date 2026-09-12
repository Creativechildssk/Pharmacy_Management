<?php
require __DIR__.'/../config/bootstrap.php';
use Pharmacy\Auth\Authorization;
Authorization::requireLogin();
$reports=new Pharmacy\Reports\ReportRepository($pdo);
$data=$reports->dashboard(date('Y-m-d'));
$nearExpiryDays=max(1,(int)($systemSettings['near_expiry_days_3']??90));
$data['near_expiry_count']=(int)$pdo->query("SELECT COUNT(*) FROM medicine_batches WHERE quantity_available>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL {$nearExpiryDays} DAY)")->fetchColumn();
$title='Dashboard';
require __DIR__.'/../templates/header.php';
?>
<h2 class="page-title">Dashboard</h2>
<div class="row g-3 mb-4">
<?php
$cards=[
['Dispenses Today',(int)($data['today']['dispenses']??0)],
['Patients Served',(int)($data['today']['patients']??0)],
['Quantity Dispensed',number_format((float)($data['today']['quantity']??0),2)],
['Consumption Value','₹'.number_format((float)($data['today']['consumption_value']??0),2)],
['Inventory Value','₹'.number_format((float)$data['inventory_value'],2)],
['Low Stock',(int)$data['low_stock_count']],
['Near Expiry',(int)$data['near_expiry_count']],
['Expired Batches',(int)$data['expired_count']],
];
foreach($cards as [$label,$value]):?>
<div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-muted small"><?=htmlspecialchars($label)?></div><div class="metric-value"><?=htmlspecialchars((string)$value)?></div></div></div></div>
<?php endforeach;?>
</div>
<div class="row g-3">
<div class="col-lg-6"><div class="card metric-card"><div class="card-body"><h5>Quick Actions</h5><div class="d-flex flex-wrap gap-2">
<?php if(Authorization::hasRole('ADMIN','PHARMACIST')):?><a class="btn btn-dark" href="/dispensing/index.php">Dispense Medicine</a><a class="btn btn-outline-dark" href="/prescriptions/index.php">New Prescription</a><?php endif;?>
<?php if(Authorization::hasRole('ADMIN','INVENTORY')):?><a class="btn btn-outline-dark" href="/receipts/index.php">Receive Stock</a><?php endif;?>
<a class="btn btn-outline-dark" href="/reports/index.php">Reports</a></div></div></div></div>
<div class="col-lg-6"><div class="card metric-card"><div class="card-body"><h5>Stock Attention</h5><p class="mb-1"><strong><?= (int)$data['low_stock_count'] ?></strong> medicines are at/below reorder level.</p><p class="mb-1"><strong><?= (int)$data['near_expiry_count'] ?></strong> batches expire within <?= $nearExpiryDays ?> days.</p><p class="mb-0"><strong><?= (int)$data['expired_count'] ?></strong> expired batches still have stock requiring write-off.</p></div></div></div>
</div>
<?php require __DIR__.'/../templates/footer.php';
