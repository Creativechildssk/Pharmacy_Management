<?php

declare(strict_types=1);

namespace Pharmacy\Inventory;

use DomainException;
use PDO;
use Throwable;

final class AdjustmentService
{
    private const OUT_TYPES = ['DAMAGE','EXPIRED','SUPPLIER_RETURN','ADJUSTMENT_OUT'];
    private const ALL_TYPES = ['DAMAGE','EXPIRED','SUPPLIER_RETURN','ADJUSTMENT_IN','ADJUSTMENT_OUT'];

    public function __construct(private PDO $pdo, private StockLedgerService $ledger) {}

    public function post(string $type, int $batchId, float $quantity, string $reason, int $userId): int
    {
        $type = strtoupper($type);
        if (!in_array($type, self::ALL_TYPES, true) || $quantity <= 0 || trim($reason) === '') {
            throw new DomainException('Valid adjustment type, quantity and reason are required');
        }

        $this->pdo->beginTransaction();
        try {
            $batchStmt = $this->pdo->prepare('SELECT * FROM medicine_batches WHERE id=:id FOR UPDATE');
            $batchStmt->execute(['id' => $batchId]);
            $batch = $batchStmt->fetch();
            if (!$batch) {
                throw new DomainException('Batch not found');
            }

            $isOut = in_array($type, self::OUT_TYPES, true);
            if ($isOut && (float) $batch['quantity_available'] + 0.000001 < $quantity) {
                throw new DomainException('Insufficient stock for adjustment');
            }

            $number = 'ADJ-' . date('YmdHis') . '-' . random_int(100,999);
            $insert = $this->pdo->prepare("INSERT INTO stock_adjustments(adjustment_no,adjustment_type,batch_id,quantity,reason,status,created_by) VALUES(:no,:type,:batch_id,:quantity,:reason,'POSTED',:user_id)");
            $insert->execute(['no' => $number, 'type' => $type, 'batch_id' => $batchId, 'quantity' => $quantity, 'reason' => trim($reason), 'user_id' => $userId]);
            $adjustmentId = (int) $this->pdo->lastInsertId();

            if ($isOut) {
                $update = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available-:qty_decrement, status=CASE WHEN quantity_available-:qty_status<=0 THEN 'DEPLETED' ELSE status END WHERE id=:id");
                $update->execute(['qty_decrement' => $quantity, 'qty_status' => $quantity, 'id' => $batchId]);
                $quantityIn = 0;
                $quantityOut = $quantity;
            } else {
                $update = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available+:qty_increment, status=CASE WHEN expiry_date<CURDATE() THEN 'EXPIRED' ELSE 'ACTIVE' END WHERE id=:id");
                $update->execute(['qty_increment' => $quantity, 'id' => $batchId]);
                $quantityIn = $quantity;
                $quantityOut = 0;
            }

            $this->ledger->record([
                'transaction_type' => $type,
                'medicine_id' => (int) $batch['medicine_id'],
                'batch_id' => $batchId,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'unit_cost' => (float) $batch['purchase_rate'],
                'transaction_value' => round($quantity * (float) $batch['purchase_rate'], 2),
                'source_type' => 'STOCK_ADJUSTMENT',
                'source_id' => $adjustmentId,
                'reason' => trim($reason),
                'user_id' => $userId,
            ]);

            $this->pdo->commit();
            return $adjustmentId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reverse(int $adjustmentId, string $reason, int $userId): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Reversal reason is required');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT a.*,b.medicine_id,b.purchase_rate,b.quantity_available FROM stock_adjustments a JOIN medicine_batches b ON b.id=a.batch_id WHERE a.id=:id FOR UPDATE');
            $stmt->execute(['id' => $adjustmentId]);
            $row = $stmt->fetch();
            if (!$row || $row['status'] !== 'POSTED') {
                throw new DomainException('Adjustment cannot be reversed');
            }

            $originalWasOut = in_array($row['adjustment_type'], self::OUT_TYPES, true);
            if (!$originalWasOut && (float) $row['quantity_available'] + 0.000001 < (float) $row['quantity']) {
                throw new DomainException('Cannot reverse adjustment because stock has already been consumed');
            }

            $delta = (float) $row['quantity'];
            if ($originalWasOut) {
                $restore = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available+:qty_restore, status=CASE WHEN expiry_date<CURDATE() THEN 'EXPIRED' ELSE 'ACTIVE' END WHERE id=:id");
                $restore->execute(['qty_restore' => $delta, 'id' => $row['batch_id']]);
            } else {
                $remove = $this->pdo->prepare("UPDATE medicine_batches SET quantity_available=quantity_available-:qty_remove, status=CASE WHEN quantity_available-:qty_status<=0 THEN 'DEPLETED' ELSE status END WHERE id=:id");
                $remove->execute(['qty_remove' => $delta, 'qty_status' => $delta, 'id' => $row['batch_id']]);
            }

            $this->ledger->record([
                'transaction_type' => 'REVERSAL',
                'medicine_id' => (int) $row['medicine_id'],
                'batch_id' => (int) $row['batch_id'],
                'quantity_in' => $originalWasOut ? $delta : 0,
                'quantity_out' => $originalWasOut ? 0 : $delta,
                'unit_cost' => (float) $row['purchase_rate'],
                'transaction_value' => round($delta * (float) $row['purchase_rate'], 2),
                'source_type' => 'ADJUSTMENT_REVERSAL',
                'source_id' => $adjustmentId,
                'reason' => trim($reason),
                'user_id' => $userId,
            ]);

            $this->pdo->prepare("UPDATE stock_adjustments SET status='REVERSED',reversed_by=:user_id,reversed_at=NOW(),reason=CONCAT(reason,' | Reversal: ',:reason) WHERE id=:id")
                ->execute(['user_id' => $userId, 'reason' => trim($reason), 'id' => $adjustmentId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
