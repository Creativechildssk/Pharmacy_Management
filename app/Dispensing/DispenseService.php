<?php

declare(strict_types=1);

namespace Pharmacy\Dispensing;

use DomainException;
use PDO;
use Pharmacy\Inventory\FefoAllocator;
use Pharmacy\Inventory\StockLedgerService;
use Throwable;

final class DispenseService
{
    public function __construct(
        private PDO $pdo,
        private StockLedgerService $ledger,
        private FefoAllocator $allocator
    ) {}

    public function post(array $command, int $pharmacistId): int
    {
        if (empty($command['items'])) {
            throw new DomainException('At least one medicine is required');
        }
        $patientType = strtoupper((string) ($command['patient_type'] ?? ''));
        if (!in_array($patientType, ['EMPLOYEE', 'EXTERNAL'], true)) {
            throw new DomainException('Invalid patient type');
        }

        $this->pdo->beginTransaction();
        try {
            $this->validatePatient($patientType, $command);
            $prescriptionId = !empty($command['prescription_id']) ? (int) $command['prescription_id'] : null;
            if ($prescriptionId !== null) {
                $this->validatePrescriptionHeader($prescriptionId, $patientType, $command);
            }

            $dispenseNo = $command['dispense_no'] ?? ('DSP-' . date('YmdHis') . '-' . random_int(100,999));
            $header = $this->pdo->prepare("INSERT INTO dispenses(dispense_no,prescription_id,patient_type,employee_id,external_patient_id,dispense_at,status,pharmacist_id,remarks) VALUES(:no,:prescription_id,:patient_type,:employee_id,:external_patient_id,NOW(),'POSTED',:pharmacist_id,:remarks)");
            $header->execute([
                'no' => $dispenseNo,
                'prescription_id' => $prescriptionId,
                'patient_type' => $patientType,
                'employee_id' => $patientType === 'EMPLOYEE' ? $command['employee_id'] : null,
                'external_patient_id' => $patientType === 'EXTERNAL' ? $command['external_patient_id'] : null,
                'pharmacist_id' => $pharmacistId,
                'remarks' => $command['remarks'] ?? null,
            ]);
            $dispenseId = (int) $this->pdo->lastInsertId();

            foreach ($command['items'] as $item) {
                $medicineId = (int) ($item['medicine_id'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 0);
                if ($medicineId <= 0 || $quantity <= 0) {
                    throw new DomainException('Medicine and positive quantity are required');
                }

                $medicine = $this->pdo->prepare('SELECT id FROM medicines WHERE id=:id AND active=1');
                $medicine->execute(['id' => $medicineId]);
                if (!$medicine->fetchColumn()) {
                    throw new DomainException('Medicine is inactive or unavailable');
                }

                $prescriptionItemId = isset($item['prescription_item_id']) ? (int) $item['prescription_item_id'] : null;
                if ($prescriptionId !== null && $prescriptionItemId === null) {
                    throw new DomainException('Every prescription dispense item must reference its prescription item');
                }
                if ($prescriptionItemId !== null) {
                    $this->validatePrescriptionItem($prescriptionId, $prescriptionItemId, $medicineId, $quantity);
                }

                $batchStmt = $this->pdo->prepare("SELECT id,expiry_date,quantity_available,purchase_rate,status FROM medicine_batches WHERE medicine_id=:medicine_id AND quantity_available>0 AND expiry_date>=CURDATE() AND status='ACTIVE' ORDER BY expiry_date ASC,id ASC FOR UPDATE");
                $batchStmt->execute(['medicine_id' => $medicineId]);
                $allocations = $this->allocator->allocate($batchStmt->fetchAll(), $quantity, date('Y-m-d'));

                foreach ($allocations as $allocation) {
                    $decrement = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available-:qty_decrement, status=CASE WHEN quantity_available-:qty_status<=0 THEN 'DEPLETED' ELSE status END WHERE id=:id AND quantity_available>=:qty_available");
                    $decrement->execute([
                        'qty_decrement' => $allocation['quantity'],
                        'qty_status' => $allocation['quantity'],
                        'id' => $allocation['batch_id'],
                        'qty_available' => $allocation['quantity'],
                    ]);
                    if ($decrement->rowCount() !== 1) {
                        throw new DomainException('Stock changed while dispensing; please retry');
                    }

                    $total = round($allocation['quantity'] * $allocation['unit_cost'], 2);
                    $line = $this->pdo->prepare('INSERT INTO dispense_items(dispense_id,medicine_id,batch_id,prescription_item_id,quantity,unit_cost,total_cost,dosage_instruction) VALUES(:dispense_id,:medicine_id,:batch_id,:prescription_item_id,:quantity,:unit_cost,:total_cost,:dosage)');
                    $line->execute([
                        'dispense_id' => $dispenseId,
                        'medicine_id' => $medicineId,
                        'batch_id' => $allocation['batch_id'],
                        'prescription_item_id' => $prescriptionItemId,
                        'quantity' => $allocation['quantity'],
                        'unit_cost' => $allocation['unit_cost'],
                        'total_cost' => $total,
                        'dosage' => $item['dosage_instruction'] ?? null,
                    ]);
                    $this->ledger->record([
                        'transaction_type' => 'DISPENSE',
                        'medicine_id' => $medicineId,
                        'batch_id' => $allocation['batch_id'],
                        'quantity_in' => 0,
                        'quantity_out' => $allocation['quantity'],
                        'unit_cost' => $allocation['unit_cost'],
                        'transaction_value' => $total,
                        'source_type' => 'DISPENSE',
                        'source_id' => $dispenseId,
                        'user_id' => $pharmacistId,
                    ]);
                }

                if ($prescriptionItemId !== null) {
                    $updateRx = $this->pdo->prepare('UPDATE prescription_items SET quantity_dispensed=quantity_dispensed+:qty WHERE id=:id');
                    $updateRx->execute(['qty' => $quantity, 'id' => $prescriptionItemId]);
                }
            }

            if ($prescriptionId !== null) {
                $this->refreshPrescriptionStatus($prescriptionId);
            }

            $this->pdo->commit();
            return $dispenseId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reverse(int $dispenseId, string $reason, int $userId): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Reversal reason is required');
        }
        $this->pdo->beginTransaction();
        try {
            $header = $this->pdo->prepare('SELECT * FROM dispenses WHERE id=:id FOR UPDATE');
            $header->execute(['id' => $dispenseId]);
            $dispense = $header->fetch();
            if (!$dispense || $dispense['status'] !== 'POSTED') {
                throw new DomainException('Dispense cannot be reversed');
            }
            $items = $this->pdo->prepare('SELECT * FROM dispense_items WHERE dispense_id=:id');
            $items->execute(['id' => $dispenseId]);
            foreach ($items->fetchAll() as $item) {
                $restore = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available+:qty, status=CASE WHEN expiry_date<CURDATE() THEN 'EXPIRED' ELSE 'ACTIVE' END WHERE id=:id");
                $restore->execute(['qty' => $item['quantity'], 'id' => $item['batch_id']]);
                $this->ledger->record([
                    'transaction_type' => 'REVERSAL',
                    'medicine_id' => (int) $item['medicine_id'],
                    'batch_id' => (int) $item['batch_id'],
                    'quantity_in' => (float) $item['quantity'],
                    'quantity_out' => 0,
                    'unit_cost' => (float) $item['unit_cost'],
                    'transaction_value' => (float) $item['total_cost'],
                    'source_type' => 'DISPENSE_REVERSAL',
                    'source_id' => $dispenseId,
                    'reason' => trim($reason),
                    'user_id' => $userId,
                ]);
                if ($item['prescription_item_id']) {
                    $rx = $this->pdo->prepare('UPDATE prescription_items SET quantity_dispensed=GREATEST(0,quantity_dispensed-:qty) WHERE id=:id');
                    $rx->execute(['qty' => $item['quantity'], 'id' => $item['prescription_item_id']]);
                }
            }
            $mark = $this->pdo->prepare("UPDATE dispenses SET status='REVERSED',reversed_by=:user_id,reversed_at=NOW(),remarks=CONCAT(COALESCE(remarks,''),' | Reversal: ',:reason) WHERE id=:id");
            $mark->execute(['user_id' => $userId, 'reason' => trim($reason), 'id' => $dispenseId]);
            if ($dispense['prescription_id']) {
                $this->refreshPrescriptionStatus((int) $dispense['prescription_id']);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function validatePatient(string $patientType, array $command): void
    {
        $table = $patientType === 'EMPLOYEE' ? 'employees' : 'external_patients';
        $key = $patientType === 'EMPLOYEE' ? 'employee_id' : 'external_patient_id';
        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE id=:id AND active=1");
        $stmt->execute(['id' => $command[$key] ?? 0]);
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Patient is inactive or unavailable');
        }
    }

    private function validatePrescriptionHeader(int $prescriptionId, string $patientType, array $command): void
    {
        $stmt = $this->pdo->prepare('SELECT patient_type,employee_id,external_patient_id,status FROM prescriptions WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $prescriptionId]);
        $prescription = $stmt->fetch();
        if (!$prescription || !in_array($prescription['status'], ['OPEN','PARTIALLY_DISPENSED'], true)) {
            throw new DomainException('Prescription is not open for dispensing');
        }
        if ($prescription['patient_type'] !== $patientType) {
            throw new DomainException('Prescription patient does not match the dispensing patient');
        }
        $expectedPatientId = $patientType === 'EMPLOYEE' ? (int) $prescription['employee_id'] : (int) $prescription['external_patient_id'];
        $commandPatientId = $patientType === 'EMPLOYEE' ? (int) ($command['employee_id'] ?? 0) : (int) ($command['external_patient_id'] ?? 0);
        if ($expectedPatientId !== $commandPatientId) {
            throw new DomainException('Prescription patient does not match the dispensing patient');
        }
    }

    private function validatePrescriptionItem(?int $prescriptionId, int $itemId, int $medicineId, float $quantity): void
    {
        if (!$prescriptionId) {
            throw new DomainException('Prescription item requires a prescription');
        }
        $stmt = $this->pdo->prepare('SELECT pi.quantity_prescribed,pi.quantity_dispensed FROM prescription_items pi WHERE pi.id=:item_id AND pi.prescription_id=:prescription_id AND pi.medicine_id=:medicine_id FOR UPDATE');
        $stmt->execute(['item_id' => $itemId, 'prescription_id' => $prescriptionId, 'medicine_id' => $medicineId]);
        $item = $stmt->fetch();
        if (!$item || ((float) $item['quantity_prescribed'] - (float) $item['quantity_dispensed']) + 0.000001 < $quantity) {
            throw new DomainException('Dispense quantity exceeds prescription balance');
        }
    }

    private function refreshPrescriptionStatus(int $prescriptionId): void
    {
        $stmt = $this->pdo->prepare('SELECT SUM(quantity_prescribed) prescribed,SUM(quantity_dispensed) dispensed FROM prescription_items WHERE prescription_id=:id');
        $stmt->execute(['id' => $prescriptionId]);
        $totals = $stmt->fetch();
        $prescribed = (float) ($totals['prescribed'] ?? 0);
        $dispensed = (float) ($totals['dispensed'] ?? 0);
        $status = $dispensed <= 0 ? 'OPEN' : ($dispensed + 0.000001 >= $prescribed ? 'DISPENSED' : 'PARTIALLY_DISPENSED');
        $update = $this->pdo->prepare("UPDATE prescriptions SET status=:status WHERE id=:id AND status<>'CANCELLED'");
        $update->execute(['status' => $status, 'id' => $prescriptionId]);
    }
}
