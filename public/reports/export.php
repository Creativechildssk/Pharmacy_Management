<?php
require __DIR__.'/../../config/bootstrap.php';
use Pharmacy\Auth\Authorization;use Pharmacy\Reports\ReportRepository;
Authorization::requireRole('ADMIN','PHARMACIST','INVENTORY','MANAGEMENT_VIEWER');$r=new ReportRepository($pdo);$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');$type=$_GET['type']??'dispensing';$filters=['from'=>$from,'to'=>$to];
$rows=match($type){'medicine'=>$r->consumptionByMedicine($filters),'division'=>$r->consumptionByDivision($filters),'stock'=>$r->currentStock(),'ledger'=>$r->stockLedger($filters),'expiry30'=>$r->expiry(30),'expiry90'=>$r->expiry(90),'supplier'=>$r->supplierPurchases($filters),default=>$r->dispensing($filters)};
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="pharmacy-'.$type.'-'.$from.'-'.$to.'.csv"');$out=fopen('php://output','wb');if($rows){fputcsv($out,array_keys($rows[0]));foreach($rows as $row)fputcsv($out,$row);}fclose($out);
