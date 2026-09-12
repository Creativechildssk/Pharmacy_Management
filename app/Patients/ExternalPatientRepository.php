<?php

declare(strict_types=1);

namespace Pharmacy\Patients;

use PDO;

final class ExternalPatientRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM external_patients WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public function search(string $query, int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        $term='%'.trim($query).'%';
        $stmt=$this->pdo->prepare("SELECT * FROM external_patients WHERE patient_code LIKE :code_query OR name LIKE :name_query OR phone LIKE :phone_query ORDER BY name LIMIT {$limit}");
        $stmt->execute(['code_query'=>$term,'name_query'=>$term,'phone_query'=>$term]);
        return $stmt->fetchAll();
    }

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt=$this->pdo->prepare('UPDATE external_patients SET name=:name,date_of_birth=:dob,age=:age,gender=:gender,phone=:phone,address=:address,notes=:notes,active=:active WHERE id=:id');
            $stmt->execute(['name'=>$data['name'],'dob'=>$data['date_of_birth']??null,'age'=>$data['age']??null,'gender'=>$data['gender']??null,'phone'=>$data['phone']??null,'address'=>$data['address']??null,'notes'=>$data['notes']??null,'active'=>(int)($data['active']??1),'id'=>$data['id']]);
            return (int)$data['id'];
        }
        $code=$data['patient_code']??('EXT-'.date('ymdHis').random_int(10,99));
        $stmt=$this->pdo->prepare('INSERT INTO external_patients(patient_code,name,date_of_birth,age,gender,phone,address,notes,active) VALUES(:code,:name,:dob,:age,:gender,:phone,:address,:notes,:active)');
        $stmt->execute(['code'=>$code,'name'=>$data['name'],'dob'=>$data['date_of_birth']??null,'age'=>$data['age']??null,'gender'=>$data['gender']??null,'phone'=>$data['phone']??null,'address'=>$data['address']??null,'notes'=>$data['notes']??null,'active'=>(int)($data['active']??1)]);
        return (int)$this->pdo->lastInsertId();
    }
}
