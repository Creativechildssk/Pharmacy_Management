<?php

declare(strict_types=1);

namespace Pharmacy\Medicines;

use PDO;

final class MedicineRepository
{
    public function __construct(private PDO $pdo) {}

    public function searchActive(string $query, int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        $term='%'.trim($query).'%';
        $stmt=$this->pdo->prepare("SELECT m.*,c.name category_name,COALESCE(SUM(CASE WHEN b.expiry_date>=CURDATE() AND b.status='ACTIVE' THEN b.quantity_available ELSE 0 END),0) valid_stock FROM medicines m LEFT JOIN medicine_categories c ON c.id=m.category_id LEFT JOIN medicine_batches b ON b.medicine_id=m.id WHERE m.active=1 AND (m.name LIKE :name_query OR m.generic_name LIKE :generic_query OR m.medicine_code LIKE :code_query OR m.barcode LIKE :barcode_query) GROUP BY m.id ORDER BY m.name LIMIT {$limit}");
        $stmt->execute(['name_query'=>$term,'generic_query'=>$term,'code_query'=>$term,'barcode_query'=>$term]);
        return $stmt->fetchAll();
    }

    public function save(array $data): int
    {
        $params=['code'=>$data['medicine_code'],'name'=>$data['name'],'generic'=>$data['generic_name']??null,'strength'=>$data['strength']??null,'dosage_form'=>$data['dosage_form']??null,'manufacturer'=>$data['manufacturer']??null,'category_id'=>$data['category_id']??null,'uom'=>$data['unit_of_measure'],'barcode'=>$data['barcode']??null,'hsn'=>$data['hsn_code']??null,'gst'=>$data['gst_rate']??null,'reorder'=>$data['reorder_level']??0,'active'=>(int)($data['active']??1)];
        if (!empty($data['id'])) {
            $stmt=$this->pdo->prepare('UPDATE medicines SET medicine_code=:code,name=:name,generic_name=:generic,strength=:strength,dosage_form=:dosage_form,manufacturer=:manufacturer,category_id=:category_id,unit_of_measure=:uom,barcode=:barcode,hsn_code=:hsn,gst_rate=:gst,reorder_level=:reorder,active=:active WHERE id=:id');
            $params['id']=$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }
        $stmt=$this->pdo->prepare('INSERT INTO medicines(medicine_code,name,generic_name,strength,dosage_form,manufacturer,category_id,unit_of_measure,barcode,hsn_code,gst_rate,reorder_level,active) VALUES(:code,:name,:generic,:strength,:dosage_form,:manufacturer,:category_id,:uom,:barcode,:hsn,:gst,:reorder,:active)');
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }
}
