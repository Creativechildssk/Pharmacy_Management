<?php
require __DIR__.'/../../config/bootstrap.php';

use Pharmacy\Auth\Authorization;
use Pharmacy\Dispensing\DispenseService;
use Pharmacy\Inventory\AdjustmentService;
use Pharmacy\Inventory\FefoAllocator;
use Pharmacy\Inventory\StockLedgerService;
use Pharmacy\Support\Csrf;

Authorization::requireRole('ADMIN');
$message=$error=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        Csrf::validate((string)($_POST['csrf']??''));
        $action=(string)($_POST['action']??'');
        $id=(int)($_POST['id']??0);
        $reason=trim((string)($_POST['reason']??''));
        if($id<=0||$reason==='') throw new DomainException('Record and reversal reason are required.');
        $ledger=new StockLedgerService($pdo);
        if($action==='dispense'){
            (new DispenseService($pdo,$ledger,new FefoAllocator()))->reverse($id,$reason,(int)$_SESSION['user']['id']);
            $message='Dispense reversed with balancing stock transactions.';
        }elseif($action==='adjustment'){
            (new AdjustmentService($pdo,$ledger))->reverse($id,$reason,(int)$_SESSION['user']['id']);
            $message='Stock adjustment reversed with a balancing transaction.';
        }else{
            throw new DomainException('Unsupported reversal type.');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

$dispenses=$pdo->query("SELECT d.id,d.dispense_no,d.dispense_at,COALESCE(e.employee_code,x.patient_code) patient_code,COALESCE(e.name,x.name) patient_name,SUM(di.quantity) quantity,SUM(di.total_cost) value,u.full_name pharmacist FROM dispenses d JOIN dispense_items di ON di.dispense_id=d.id LEFT JOIN employees e ON e.id=d.employee_id LEFT JOIN external_patients x ON x.id=d.external_patient_id JOIN users u ON u.id=d.pharmacist_id WHERE d.status='POSTED' GROUP BY d.id ORDER BY d.id DESC LIMIT 100")->fetchAll();
$adjustments=$pdo->query("SELECT a.id,a.adjustment_no,a.adjustment_type,a.quantity,a.reason,a.created_at,m.name medicine,b.batch_no,u.full_name user_name FROM stock_adjustments a JOIN medicine_batches b ON b.id=a.batch_id JOIN medicines m ON m.id=b.medicine_id JOIN users u ON u.id=a.created_by WHERE a.status='POSTED' ORDER BY a.id DESC LIMIT 100")->fetchAll();
$title='Reversal Center';require __DIR__.'/../../templates/header.php';
?>
<h2 class="page-title">Reversal Center</h2>
<div class="alert alert-warning"><strong>Admin control:</strong> Reversal does not delete history. It posts balancing stock ledger entries and marks the original transaction reversed.</div>
<?php if($message):?><div data-flash="<?=htmlspecialchars($message)?>"></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>

<div class="card mb-4"><div class="card-body"><h5>Posted Dispenses</h5><div class="table-responsive"><table class="table datatable"><thead><tr><th>No.</th><th>Date</th><th>Patient</th><th>Qty</th><th>Value</th><th>Pharmacist</th><th>Reverse</th></tr></thead><tbody>
<?php foreach($dispenses as $d):?><tr><td><?=htmlspecialchars($d['dispense_no'])?></td><td><?=htmlspecialchars($d['dispense_at'])?></td><td><?=htmlspecialchars(($d['patient_code']??'').' - '.($d['patient_name']??''))?></td><td><?=number_format((float)$d['quantity'],3)?></td><td>₹<?=number_format((float)$d['value'],2)?></td><td><?=htmlspecialchars($d['pharmacist'])?></td><td><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="action" value="dispense"><input type="hidden" name="id" value="<?=$d['id']?>"><input class="form-control form-control-sm" name="reason" placeholder="Mandatory reason" required><button class="btn btn-sm btn-outline-danger" data-confirm="Reverse this dispense and restore stock?">Reverse</button></form></td></tr><?php endforeach;?>
</tbody></table></div></div></div>

<div class="card"><div class="card-body"><h5>Posted Stock Adjustments</h5><div class="table-responsive"><table class="table datatable"><thead><tr><th>No.</th><th>Type</th><th>Medicine</th><th>Batch</th><th>Qty</th><th>Original Reason</th><th>Reverse</th></tr></thead><tbody>
<?php foreach($adjustments as $a):?><tr><td><?=htmlspecialchars($a['adjustment_no'])?></td><td><?=htmlspecialchars($a['adjustment_type'])?></td><td><?=htmlspecialchars($a['medicine'])?></td><td><?=htmlspecialchars($a['batch_no'])?></td><td><?=number_format((float)$a['quantity'],3)?></td><td><?=htmlspecialchars($a['reason'])?></td><td><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="action" value="adjustment"><input type="hidden" name="id" value="<?=$a['id']?>"><input class="form-control form-control-sm" name="reason" placeholder="Mandatory reason" required><button class="btn btn-sm btn-outline-danger" data-confirm="Reverse this stock adjustment?">Reverse</button></form></td></tr><?php endforeach;?>
</tbody></table></div></div></div>
<?php require __DIR__.'/../../templates/footer.php';
