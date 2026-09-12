<?php

declare(strict_types=1);

namespace Pharmacy\Employees;

use PDO;
use RuntimeException;

final class EmployeeImportService
{
    public function __construct(private PDO $pdo, private EmployeeRepository $repository) {}

    public function importCsv(string $path, int $userId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open employee import file');
        }

        $header = fgetcsv($handle);
        $expected = ['employee_code','name','division','department','designation','phone','employment_status','active'];
        if ($header === false || array_map('trim', $header) !== $expected) {
            fclose($handle);
            throw new RuntimeException('Invalid CSV header');
        }

        $result = ['inserted'=>0,'updated'=>0,'rejected'=>0,'errors'=>[]];
        $seen = [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($expected, array_pad($row, count($expected), null));
            $code = trim((string) ($data['employee_code'] ?? ''));
            $name = trim((string) ($data['name'] ?? ''));
            if ($code === '' || $name === '' || isset($seen[$code])) {
                $result['rejected']++;
                $result['errors'][] = "Row {$rowNumber}: missing/duplicate employee code or name";
                continue;
            }
            $seen[$code] = true;

            $existing = $this->repository->findByCode($code);
            $payload = [
                'id' => $existing['id'] ?? null,
                'employee_code' => $code,
                'name' => $name,
                'division' => trim((string) $data['division']) ?: null,
                'department' => trim((string) $data['department']) ?: null,
                'designation' => trim((string) $data['designation']) ?: null,
                'phone' => trim((string) $data['phone']) ?: null,
                'employment_status' => trim((string) $data['employment_status']) ?: 'ACTIVE',
                'active' => in_array(strtolower(trim((string) $data['active'])), ['1','true','yes','active'], true) ? 1 : 0,
            ];
            $this->repository->save($payload);
            $existing ? $result['updated']++ : $result['inserted']++;
        }
        fclose($handle);
        return $result;
    }
}
