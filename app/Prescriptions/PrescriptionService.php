<?php

declare(strict_types=1);

namespace Pharmacy\Prescriptions;

use DomainException;
use PDO;
use Throwable;

final class PrescriptionService
{
    public function __construct(private PDO $pdo) {}

    public function create(array $command, int $userId): int
    {
        $patientType = strtoupper((string) ($command['patient_type'] ?? ''));
        if (!in_array($patientType, ['EMPLOYEE', 'EXTERNAL'], true)) {
            throw new DomainException('Invalid patient type');
        }
        if (empty($command['doctor_id']) || empty($command['visit_date']) || empty($command['items'])) {
            throw new DomainException('Doctor, visit date and medicines are required');
        }

        $this->pdo->beginTransaction();
        try {
            $this->validatePatient($patientType, $command);
            $doctor = $this->pdo->prepare('SELECT id FROM doctors WHERE id=:id AND active=1');
            $doctor->execute(['id' => $command['doctor_id']]);
            if (!$doctor->fetchColumn()) {
                throw new DomainException('Doctor is inactive or unavailable');
            }

            $number = $command['prescription_no'] ?? ('RX-' . date('YmdHis') . '-' . random_int(100, 999));
            $insert = $this->pdo->prepare("INSERT INTO prescriptions(prescription_no,patient_type,employee_id,external_patient_id,doctor_id,visit_date,diagnosis_notes,prescription_notes,status,entered_by) VALUES(:no,:patient_type,:employee_id,:external_patient_id,:doctor_id,:visit_date,:diagnosis,:notes,'OPEN',:entered_by)");
            $insert->execute([
                'no' => $number,
                'patient_type' => $patientType,
                'employee_id' => $patientType === 'EMPLOYEE' ? $command['employee_id'] : null,
                'external_patient_id' => $patientType === 'EXTERNAL' ? $command['external_patient_id'] : null,
                'doctor_id' => $command['doctor_id'],
                'visit_date' => $command['visit_date'],
                'diagnosis' => $command['diagnosis_notes'] ?? null,
                'notes' => $command['prescription_notes'] ?? null,
                'entered_by' => $userId,
            ]);
            $prescriptionId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare('INSERT INTO prescription_items(prescription_id,medicine_id,dosage_instruction,frequency,duration,quantity_prescribed,remarks) VALUES(:prescription_id,:medicine_id,:dosage,:frequency,:duration,:quantity,:remarks)');
            foreach ($command['items'] as $item) {
                $quantity = (float) ($item['quantity_prescribed'] ?? 0);
                if ($quantity <= 0) {
                    throw new DomainException('Prescribed quantity must be greater than zero');
                }
                $medicine = $this->pdo->prepare('SELECT id FROM medicines WHERE id=:id AND active=1');
                $medicine->execute(['id' => $item['medicine_id']]);
                if (!$medicine->fetchColumn()) {
                    throw new DomainException('Medicine is inactive or unavailable');
                }
                $itemStmt->execute([
                    'prescription_id' => $prescriptionId,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage_instruction'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'quantity' => $quantity,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            $this->pdo->commit();
            return $prescriptionId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function remainingQuantity(int $prescriptionItemId): float
    {
        $stmt = $this->pdo->prepare('SELECT quantity_prescribed-quantity_dispensed FROM prescription_items WHERE id=:id');
        $stmt->execute(['id' => $prescriptionItemId]);
        $remaining = $stmt->fetchColumn();
        if ($remaining === false) {
            throw new DomainException('Prescription item not found');
        }
        return max(0.0, (float) $remaining);
    }

    private function validatePatient(string $patientType, array $command): void
    {
        if ($patientType === 'EMPLOYEE') {
            $stmt = $this->pdo->prepare('SELECT id FROM employees WHERE id=:id AND active=1');
            $stmt->execute(['id' => $command['employee_id'] ?? 0]);
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM external_patients WHERE id=:id AND active=1');
            $stmt->execute(['id' => $command['external_patient_id'] ?? 0]);
        }
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Patient is inactive or unavailable');
        }
    }
}
