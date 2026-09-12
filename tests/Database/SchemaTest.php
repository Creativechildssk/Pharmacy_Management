<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function test_schema_contains_core_inventory_and_audit_tables(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        self::assertIsString($sql);
        foreach (['medicine_batches', 'stock_transactions', 'dispenses', 'dispense_items', 'audit_logs'] as $table) {
            self::assertStringContainsString("CREATE TABLE {$table}", $sql);
        }
        self::assertStringContainsString('CHECK (quantity_available >= 0)', $sql);
    }
}
