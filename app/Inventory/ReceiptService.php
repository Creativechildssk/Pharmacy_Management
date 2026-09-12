<?php

declare(strict_types=1);

namespace Pharmacy\Inventory;

use DomainException;
use PDO;
use Pharmacy\Audit\AuditLogger;
use Throwable;

final class ReceiptService
{
    public function __construct(private PDO $pdo, private StockLedgerService $ledger) {}

    public function post(int $receiptId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $header = $this->pdo->prepare('SELECT * FROM stock_receipts WHERE id=:id FOR UPDATE');
            $header->execute(['id' => $receiptId]);
            $receipt = $header->fetch();
            if (!$receipt || $receipt['status'] !== 'DRAFT') {
                throw new DomainException('Receipt is not available for posting');
            }

            $dup = $this->pdo->prepare("SELECT COUNT(*) FROM stock_receipts WHERE supplier_id=:supplier_id AND supplier_invoice_no=:invoice AND status='POSTED' AND id<>:id");
            $dup->execute(['supplier_id' => $receipt['supplier_id'], 'invoice' => $receipt['supplier_invoice_no'], 'id' => $receiptId]);
            if ((int) $dup->fetchColumn() > 0) {
                throw new DomainException('Supplier invoice has already been posted');
            }

            $items = $this->pdo->prepare('SELECT * FROM stock_receipt_items WHERE receipt_id=:id ORDER BY id');
            $items->execute(['id' => $receiptId]);
            $rows = $items->fetchAll();
            if (!$rows) {
                throw new DomainException('Receipt has no items');
            }

            foreach ($rows as $item) {
                $received = (float) $item['received_quantity'] + (float) $item['free_quantity'];
                if ($received <= 0 || (float) $item['purchase_rate'] < 0) {
                    throw new DomainException('Invalid receipt item quantity or rate');
                }
                if ($item['expiry_date'] < $receipt['receipt_date']) {
                    throw new DomainException('Cannot receive an expired batch');
                }

                $find = $this->pdo->prepare('SELECT * FROM medicine_batches WHERE medicine_id=:medicine_id AND batch_no=:batch_no AND expiry_date=:expiry_date FOR UPDATE');
                $find->execute(['medicine_id' => $item['medicine_id'], 'batch_no' => $item['batch_no'], 'expiry_date' => $item['expiry_date']]);
                $batch = $find->fetch();

                if ($batch) {
                    $batchId = (int) $batch['id'];
                    $update = $this->pdo->prepare("UPDATE medicine_batches SET quantity_received=quantity_received+:qty_received_add, quantity_available=quantity_available+:qty_available_add, purchase_rate=:rate, status='ACTIVE' WHERE id=:id");
                    $update->execute([
                        'qty_received_add' => $received,
                        'qty_available_add' => $received,
                        'rate' => $item['purchase_rate'],
                        'id' => $batchId,
                    ]);
                } else {
                    $insert = $this->pdo->prepare("INSERT INTO medicine_batches(medicine_id,batch_no,manufacture_date,expiry_date,purchase_rate,supplier_id,receipt_item_id,quantity_received,quantity_available,status) VALUES(:medicine_id,:batch_no,:manufacture_date,:expiry_date,:purchase_rate,:supplier_id,:receipt_item_id,:qty_received,:qty_available,'ACTIVE')");
                    $insert->execute([
                        'medicine_id' => $item['medicine_id'],
                        'batch_no' => $item['batch_no'],
                        'manufacture_date' => $item['manufacture_date'],
                        'expiry_date' => $item['expiry_date'],
                        'purchase_rate' => $item['purchase_rate'],
                        'supplier_id' => $receipt['supplier_id'],
                        'receipt_item_id' => $item['id'],
                        'qty_received' => $received,
                        'qty_available' => $received,
                    ]);
                    $batchId = (int) $this->pdo->lastInsertId();
                }

                $this->ledger->record([
                    'transaction_type' => 'RECEIPT',
                    'medicine_id' => (int) $item['medicine_id'],
                    'batch_id' => $batchId,
                    'quantity_in' => $received,
                    'quantity_out' => 0,
                    'unit_cost' => (float) $item['purchase_rate'],
                    'transaction_value' => round($received * (float) $item['purchase_rate'], 2),
                    'source_type' => 'STOCK_RECEIPT',
                    'source_id' => $receiptId,
                    'user_id' => $userId,
                ]);
            }

            $post = $this->pdo->prepare("UPDATE stock_receipts SET status='POSTED', posted_by=:user_id, posted_at=NOW() WHERE id=:id");
            $post->execute(['user_id' => $userId, 'id' => $receiptId]);
            (new AuditLogger($this->pdo))->log($userId, 'POST', 'INVENTORY', 'stock_receipt', $receiptId, ['status'=>'DRAFT'], ['status'=>'POSTED','items'=>count($rows)], $_SERVER['REMOTE_ADDR'] ?? null);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
