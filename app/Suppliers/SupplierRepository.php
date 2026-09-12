<?php

declare(strict_types=1);

namespace Pharmacy\Suppliers;

use PDO;

final class SupplierRepository
{
    public function __construct(private PDO $pdo) {}

    public function active(): array
    {
        return $this->pdo->query('SELECT * FROM suppliers WHERE active=1 ORDER BY name')->fetchAll();
    }

    public function save(array $data): int
    {
        $params=['code'=>$data['supplier_code'],'name'=>$data['name'],'address'=>$data['address']??null,'phone'=>$data['phone']??null,'email'=>$data['email']??null,'gst'=>$data['gst_no']??null,'contact'=>$data['contact_person']??null,'active'=>(int)($data['active']??1)];
        if (!empty($data['id'])) {
            $stmt=$this->pdo->prepare('UPDATE suppliers SET supplier_code=:code,name=:name,address=:address,phone=:phone,email=:email,gst_no=:gst,contact_person=:contact,active=:active WHERE id=:id');
            $params['id']=$data['id'];
            $stmt->execute($params);
            return (int)$data['id'];
        }
        $stmt=$this->pdo->prepare('INSERT INTO suppliers(supplier_code,name,address,phone,email,gst_no,contact_person,active) VALUES(:code,:name,:address,:phone,:email,:gst,:contact,:active)');
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }
}
