<?php

declare(strict_types=1);

namespace Tests\Inventory;

use DomainException;
use Pharmacy\Inventory\FefoAllocator;
use PHPUnit\Framework\TestCase;

final class FefoAllocatorTest extends TestCase
{
    public function test_allocates_nearest_valid_expiry_first_and_skips_expired_batches(): void
    {
        $batches = [
            ['id' => 1, 'expiry_date' => '2026-12-31', 'quantity_available' => 5.0, 'purchase_rate' => 2.00, 'status' => 'ACTIVE'],
            ['id' => 2, 'expiry_date' => '2027-01-31', 'quantity_available' => 10.0, 'purchase_rate' => 2.10, 'status' => 'ACTIVE'],
            ['id' => 3, 'expiry_date' => '2026-09-01', 'quantity_available' => 100.0, 'purchase_rate' => 1.90, 'status' => 'ACTIVE'],
        ];

        $result = (new FefoAllocator())->allocate($batches, 8.0, '2026-09-12');

        self::assertSame([
            ['batch_id' => 1, 'quantity' => 5.0, 'expiry_date' => '2026-12-31', 'unit_cost' => 2.0],
            ['batch_id' => 2, 'quantity' => 3.0, 'expiry_date' => '2027-01-31', 'unit_cost' => 2.1],
        ], $result);
    }

    public function test_rejects_request_when_valid_stock_is_insufficient(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient valid stock');

        (new FefoAllocator())->allocate([
            ['id' => 1, 'expiry_date' => '2026-12-31', 'quantity_available' => 5.0, 'purchase_rate' => 2.00, 'status' => 'ACTIVE'],
        ], 6.0, '2026-09-12');
    }
}
