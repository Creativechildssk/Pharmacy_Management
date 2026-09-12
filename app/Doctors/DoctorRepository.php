<?php

declare(strict_types=1);

namespace Pharmacy\Doctors;

use PDO;

final class DoctorRepository
{
    public function __construct(private PDO $pdo) {}

    public function active(): array
    {
        return $this->pdo->query('SELECT * FROM doctors WHERE active=1 ORDER BY name')->fetchAll();
    }

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt=$this->pdo->prepare('UPDATE doctors SET name=:name,specialization=:specialization,registration_no=:registration_no,phone=:phone,visiting_schedule=:schedule,active=:active WHERE id=:id');
            $stmt->execute(['name'=>$data['name'],'specialization'=>$data['specialization']??null,'registration_no'=>$data['registration_no']??null,'phone'=>$data['phone']??null,'schedule'=>$data['visiting_schedule']??null,'active'=>(int)($data['active']??1),'id'=>$data['id']]);
            return (int)$data['id'];
        }
        $stmt=$this->pdo->prepare('INSERT INTO doctors(name,specialization,registration_no,phone,visiting_schedule,active) VALUES(:name,:specialization,:registration_no,:phone,:schedule,:active)');
        $stmt->execute(['name'=>$data['name'],'specialization'=>$data['specialization']??null,'registration_no'=>$data['registration_no']??null,'phone'=>$data['phone']??null,'schedule'=>$data['visiting_schedule']??null,'active'=>(int)($data['active']??1)]);
        return (int)$this->pdo->lastInsertId();
    }
}
