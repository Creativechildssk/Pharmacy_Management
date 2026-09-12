<?php

declare(strict_types=1);

namespace Pharmacy\Reports;

use PDO;

final class ReportRepository
{
    public function __construct(private PDO $pdo) {}

    public function dashboard(string $date): array
    {
        $stmt=$this->pdo->prepare("SELECT COUNT(DISTINCT d.id) dispenses,COUNT(DISTINCT CONCAT(d.patient_type,':',COALESCE(d.employee_id,d.external_patient_id))) patients,COALESCE(SUM(di.quantity),0) quantity,COALESCE(SUM(di.total_cost),0) consumption_value FROM dispenses d LEFT JOIN dispense_items di ON di.dispense_id=d.id WHERE DATE(d.dispense_at)=:date AND d.status='POSTED'");
        $stmt->execute(['date'=>$date]);
        $today=$stmt->fetch() ?: [];
        return [
            'today'=>$today,
            'inventory_value'=>$this->inventoryValue(),
            'low_stock_count'=>(int)$this->pdo->query("SELECT COUNT(*) FROM medicines m LEFT JOIN (SELECT medicine_id,SUM(CASE WHEN expiry_date>=CURDATE() AND status='ACTIVE' THEN quantity_available ELSE 0 END) qty FROM medicine_batches GROUP BY medicine_id) s ON s.medicine_id=m.id WHERE m.active=1 AND COALESCE(s.qty,0)<=m.reorder_level")->fetchColumn(),
            'near_expiry_count'=>(int)$this->pdo->query("SELECT COUNT(*) FROM medicine_batches WHERE quantity_available>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)")->fetchColumn(),
            'expired_count'=>(int)$this->pdo->query("SELECT COUNT(*) FROM medicine_batches WHERE quantity_available>0 AND expiry_date<CURDATE()")->fetchColumn(),
        ];
    }

    public function inventoryValue(): float
    {
        return (float)$this->pdo->query("SELECT COALESCE(SUM(quantity_available*purchase_rate),0) FROM medicine_batches WHERE quantity_available>0 AND expiry_date>=CURDATE() AND status='ACTIVE'")->fetchColumn();
    }

    public function currentStock(array $filters=[]): array
    {
        $sql="SELECT m.medicine_code,m.name,m.generic_name,m.unit_of_measure,b.batch_no,b.expiry_date,b.quantity_available,b.purchase_rate,(b.quantity_available*b.purchase_rate) stock_value,DATEDIFF(b.expiry_date,CURDATE()) days_to_expiry FROM medicine_batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.quantity_available>0";
        $params=[];
        if (!empty($filters['medicine_id'])) {$sql.=' AND m.id=:medicine_id';$params['medicine_id']=$filters['medicine_id'];}
        $sql.=' ORDER BY m.name,b.expiry_date';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function stockLedger(array $filters=[]): array
    {
        $sql="SELECT st.*,m.name medicine_name,b.batch_no,u.full_name user_name FROM stock_transactions st JOIN medicines m ON m.id=st.medicine_id JOIN medicine_batches b ON b.id=st.batch_id JOIN users u ON u.id=st.user_id WHERE 1=1";
        $params=[];
        if (!empty($filters['from'])) {$sql.=' AND DATE(st.created_at)>=:from';$params['from']=$filters['from'];}
        if (!empty($filters['to'])) {$sql.=' AND DATE(st.created_at)<=:to';$params['to']=$filters['to'];}
        if (!empty($filters['medicine_id'])) {$sql.=' AND st.medicine_id=:medicine_id';$params['medicine_id']=$filters['medicine_id'];}
        $sql.=' ORDER BY st.created_at DESC,st.id DESC';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function dispensing(array $filters=[]): array
    {
        $sql="SELECT d.dispense_no,d.dispense_at,d.patient_type,COALESCE(e.employee_code,p.patient_code) patient_code,COALESCE(e.name,p.name) patient_name,m.name medicine_name,b.batch_no,di.quantity,di.unit_cost,di.total_cost,u.full_name pharmacist FROM dispenses d JOIN dispense_items di ON di.dispense_id=d.id JOIN medicines m ON m.id=di.medicine_id JOIN medicine_batches b ON b.id=di.batch_id LEFT JOIN employees e ON e.id=d.employee_id LEFT JOIN external_patients p ON p.id=d.external_patient_id JOIN users u ON u.id=d.pharmacist_id WHERE d.status='POSTED'";
        $params=[];
        if (!empty($filters['from'])) {$sql.=' AND DATE(d.dispense_at)>=:from';$params['from']=$filters['from'];}
        if (!empty($filters['to'])) {$sql.=' AND DATE(d.dispense_at)<=:to';$params['to']=$filters['to'];}
        $sql.=' ORDER BY d.dispense_at DESC';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function consumptionByMedicine(array $filters=[]): array
    {
        $sql="SELECT m.medicine_code,m.name,SUM(di.quantity) quantity,SUM(di.total_cost) value FROM dispense_items di JOIN dispenses d ON d.id=di.dispense_id JOIN medicines m ON m.id=di.medicine_id WHERE d.status='POSTED'";
        $params=[];
        if (!empty($filters['from'])) {$sql.=' AND DATE(d.dispense_at)>=:from';$params['from']=$filters['from'];}
        if (!empty($filters['to'])) {$sql.=' AND DATE(d.dispense_at)<=:to';$params['to']=$filters['to'];}
        $sql.=' GROUP BY m.id ORDER BY value DESC';$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function consumptionByDivision(array $filters=[]): array
    {
        $sql="SELECT COALESCE(e.division,'External') division,SUM(di.quantity) quantity,SUM(di.total_cost) value FROM dispense_items di JOIN dispenses d ON d.id=di.dispense_id LEFT JOIN employees e ON e.id=d.employee_id WHERE d.status='POSTED'";
        $params=[];
        if (!empty($filters['from'])) {$sql.=' AND DATE(d.dispense_at)>=:from';$params['from']=$filters['from'];}
        if (!empty($filters['to'])) {$sql.=' AND DATE(d.dispense_at)<=:to';$params['to']=$filters['to'];}
        $sql.=' GROUP BY division ORDER BY value DESC';$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public function patientHistory(string $type, int $patientId): array
    {
        $field=strtoupper($type)==='EMPLOYEE'?'employee_id':'external_patient_id';
        $stmt=$this->pdo->prepare("SELECT d.dispense_no,d.dispense_at,m.name medicine_name,b.batch_no,di.quantity,di.total_cost,di.dosage_instruction FROM dispenses d JOIN dispense_items di ON di.dispense_id=d.id JOIN medicines m ON m.id=di.medicine_id JOIN medicine_batches b ON b.id=di.batch_id WHERE d.{$field}=:id AND d.status='POSTED' ORDER BY d.dispense_at DESC");
        $stmt->execute(['id'=>$patientId]);return $stmt->fetchAll();
    }

    public function expiry(int $days): array
    {
        $days=max(0,min(3650,$days));
        $stmt=$this->pdo->prepare("SELECT m.name,b.batch_no,b.expiry_date,b.quantity_available,b.purchase_rate,(b.quantity_available*b.purchase_rate) value,DATEDIFF(b.expiry_date,CURDATE()) days_remaining FROM medicine_batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.quantity_available>0 AND b.expiry_date<=DATE_ADD(CURDATE(),INTERVAL {$days} DAY) ORDER BY b.expiry_date");
        $stmt->execute();return $stmt->fetchAll();
    }

    public function supplierPurchases(array $filters=[]): array
    {
        $sql="SELECT s.name supplier,r.receipt_no,r.supplier_invoice_no,r.receipt_date,SUM(i.line_value) value FROM stock_receipts r JOIN stock_receipt_items i ON i.receipt_id=r.id JOIN suppliers s ON s.id=r.supplier_id WHERE r.status='POSTED'";
        $params=[];
        if (!empty($filters['from'])) {$sql.=' AND r.receipt_date>=:from';$params['from']=$filters['from'];}
        if (!empty($filters['to'])) {$sql.=' AND r.receipt_date<=:to';$params['to']=$filters['to'];}
        $sql.=' GROUP BY r.id ORDER BY r.receipt_date DESC';$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }
}
