<?php

declare(strict_types=1);

namespace Pharmacy\Inventory;

use PDO;

final class StockLedgerService
{
    public function __construct(private PDO $pdo) {}

    public function record(array $movement): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_transactions(transaction_type,medicine_id,batch_id,quantity_in,quantity_out,unit_cost,transaction_value,source_type,source_id,reason,user_id) VALUES(:transaction_type,:medicine_id,:batch_id,:quantity_in,:quantity_out,:unit_cost,:transaction_value,:source_type,:source_id,:reason,:user_id)');
        $stmt->execute([
            'transaction_type' => $movement['transaction_type'],
            'medicine_id' => $movement['medicine_id'],
            'batch_id' => $movement['batch_id'],
            'quantity_in' => $movement['quantity_in'] ?? 0,
            'quantity_out' => $movement['quantity_out'] ?? 0,
            'unit_cost' => $movement['unit_cost'],
            'transaction_value' => $movement['transaction_value'],
            'source_type' => $movement['source_type'],
            'source_id' => $movement['source_id'],
            'reason' => $movement['reason'] ?? null,
            'user_id' => $movement['user_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
