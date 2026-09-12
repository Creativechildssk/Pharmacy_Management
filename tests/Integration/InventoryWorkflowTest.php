<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use Pharmacy\Dispensing\DispenseService;
use Pharmacy\Inventory\FefoAllocator;
use Pharmacy\Inventory\ReceiptService;
use Pharmacy\Inventory\StockLedgerService;
use PHPUnit\Framework\TestCase;

final class InventoryWorkflowTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_DB_DSN');
        if (!$dsn) {
            self::markTestSkipped('TEST_DB_DSN is not configured');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    public function test_receipt_then_dispense_uses_fefo_and_reconciles_ledger(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userId = $this->insert('users', [
            'username' => 'tester_' . $suffix,
            'full_name' => 'Integration Tester',
            'password_hash' => password_hash('IntegrationPassword123!', PASSWORD_DEFAULT),
            'active' => 1,
        ]);
        $employeeId = $this->insert('employees', [
            'employee_code' => 'EMP-' . $suffix,
            'name' => 'Test Employee',
            'employment_status' => 'ACTIVE',
            'active' => 1,
        ]);
        $medicineId = $this->insert('medicines', [
            'medicine_code' => 'MED-' . $suffix,
            'name' => 'Test Medicine',
            'unit_of_measure' => 'tablet',
            'reorder_level' => 2,
            'active' => 1,
        ]);
        $supplierId = $this->insert('suppliers', [
            'supplier_code' => 'SUP-' . $suffix,
            'name' => 'Test Supplier',
            'active' => 1,
        ]);

        $receiptNo = 'RCV-' . $suffix;
        $receiptId = $this->insert('stock_receipts', [
            'receipt_no' => $receiptNo,
            'supplier_id' => $supplierId,
            'supplier_invoice_no' => 'INV-' . $suffix,
            'invoice_date' => date('Y-m-d'),
            'receipt_date' => date('Y-m-d'),
            'status' => 'DRAFT',
            'created_by' => $userId,
        ]);

        $expiryA = date('Y-m-d', strtotime('+90 days'));
        $expiryB = date('Y-m-d', strtotime('+180 days'));
        $this->insertReceiptItem($receiptId, $medicineId, 'A-' . $suffix, $expiryA, 5.0, 2.00);
        $this->insertReceiptItem($receiptId, $medicineId, 'B-' . $suffix, $expiryB, 10.0, 2.20);

        $ledger = new StockLedgerService($this->pdo);
        (new ReceiptService($this->pdo, $ledger))->post($receiptId, $userId);

        $batches = $this->pdo->prepare('SELECT id,batch_no,quantity_available FROM medicine_batches WHERE medicine_id=:medicine ORDER BY expiry_date');
        $batches->execute(['medicine' => $medicineId]);
        $before = $batches->fetchAll();
        self::assertCount(2, $before);
        self::assertSame(5.0, (float) $before[0]['quantity_available']);
        self::assertSame(10.0, (float) $before[1]['quantity_available']);

        $dispenseId = (new DispenseService($this->pdo, $ledger, new FefoAllocator()))->post([
            'patient_type' => 'EMPLOYEE',
            'employee_id' => $employeeId,
            'external_patient_id' => null,
            'remarks' => 'Integration FEFO test',
            'items' => [[
                'medicine_id' => $medicineId,
                'quantity' => 8.0,
                'dosage_instruction' => '1-0-1',
            ]],
        ], $userId);

        self::assertGreaterThan(0, $dispenseId);
        $batches->execute(['medicine' => $medicineId]);
        $after = $batches->fetchAll();
        self::assertSame(0.0, (float) $after[0]['quantity_available'], 'Earliest batch must be depleted first');
        self::assertSame(7.0, (float) $after[1]['quantity_available'], 'Remaining quantity must come from the later batch');

        $out = $this->pdo->prepare("SELECT COALESCE(SUM(quantity_out),0) FROM stock_transactions WHERE medicine_id=:medicine AND transaction_type='DISPENSE'");
        $out->execute(['medicine' => $medicineId]);
        self::assertSame(8.0, (float) $out->fetchColumn());

        $in = $this->pdo->prepare("SELECT COALESCE(SUM(quantity_in),0) FROM stock_transactions WHERE medicine_id=:medicine AND transaction_type='RECEIPT'");
        $in->execute(['medicine' => $medicineId]);
        self::assertSame(15.0, (float) $in->fetchColumn());

        $audit = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE record_type='dispense' AND record_id=:id");
        $audit->execute(['id' => $dispenseId]);
        self::assertSame(1, (int) $audit->fetchColumn(), 'Dispense must be audit logged atomically');
    }

    private function insertReceiptItem(int $receiptId, int $medicineId, string $batchNo, string $expiry, float $qty, float $rate): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO stock_receipt_items(receipt_id,medicine_id,batch_no,expiry_date,received_quantity,free_quantity,purchase_rate,tax_rate,line_value) VALUES(:receipt,:medicine,:batch,:expiry,:qty,0,:rate,0,:value)');
        $stmt->execute([
            'receipt' => $receiptId,
            'medicine' => $medicineId,
            'batch' => $batchNo,
            'expiry' => $expiry,
            'qty' => $qty,
            'rate' => $rate,
            'value' => round($qty * $rate, 2),
        ]);
    }

    private function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s(%s) VALUES(%s)',
            $table,
            implode(',', $columns),
            implode(',', array_map(static fn(string $column): string => ':' . $column, $columns))
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }
}
