<?php

declare(strict_types=1);

namespace Pharmacy\Employees;

use PDO;

final class EmployeeRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByCode(string $employeeCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE employee_code=:code LIMIT 1');
        $stmt->execute(['code' => trim($employeeCode)]);
        return $stmt->fetch() ?: null;
    }

    public function search(string $query, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $term = '%' . trim($query) . '%';
        $stmt = $this->pdo->prepare("SELECT id,employee_code,name,division,department,designation,active FROM employees WHERE employee_code LIKE :code_query OR name LIKE :name_query ORDER BY name LIMIT {$limit}");
        $stmt->execute(['code_query' => $term, 'name_query' => $term]);
        return $stmt->fetchAll();
    }

    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare('UPDATE employees SET employee_code=:code,name=:name,division=:division,department=:department,designation=:designation,phone=:phone,employment_status=:status,active=:active WHERE id=:id');
            $stmt->execute(['code'=>$data['employee_code'],'name'=>$data['name'],'division'=>$data['division']??null,'department'=>$data['department']??null,'designation'=>$data['designation']??null,'phone'=>$data['phone']??null,'status'=>$data['employment_status']??'ACTIVE','active'=>(int)($data['active']??1),'id'=>$data['id']]);
            return (int) $data['id'];
        }
        $stmt = $this->pdo->prepare('INSERT INTO employees(employee_code,name,division,department,designation,phone,employment_status,active) VALUES(:code,:name,:division,:department,:designation,:phone,:status,:active)');
        $stmt->execute(['code'=>$data['employee_code'],'name'=>$data['name'],'division'=>$data['division']??null,'department'=>$data['department']??null,'designation'=>$data['designation']??null,'phone'=>$data['phone']??null,'status'=>$data['employment_status']??'ACTIVE','active'=>(int)($data['active']??1)]);
        return (int) $this->pdo->lastInsertId();
    }
}
