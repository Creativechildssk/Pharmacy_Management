<?php

declare(strict_types=1);

namespace Pharmacy\Inventory;

use DomainException;
use InvalidArgumentException;

final class FefoAllocator
{
    /**
     * @param array<int,array{id:int,expiry_date:string,quantity_available:float|int|string,purchase_rate:float|int|string,status:string}> $batches
     * @return array<int,array{batch_id:int,quantity:float,expiry_date:string,unit_cost:float}>
     */
    public function allocate(array $batches, float $quantity, string $asOfDate): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero');
        }

        $eligible = array_values(array_filter($batches, static function (array $batch) use ($asOfDate): bool {
            return ($batch['status'] ?? '') === 'ACTIVE'
                && (float) ($batch['quantity_available'] ?? 0) > 0
                && (string) ($batch['expiry_date'] ?? '') >= $asOfDate;
        }));

        usort($eligible, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) $a['expiry_date'], (string) $b['expiry_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return (int) $a['id'] <=> (int) $b['id'];
        });

        $remaining = $quantity;
        $allocations = [];

        foreach ($eligible as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $batch['quantity_available'];
            $take = min($available, $remaining);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'batch_id' => (int) $batch['id'],
                'quantity' => (float) $take,
                'expiry_date' => (string) $batch['expiry_date'],
                'unit_cost' => (float) $batch['purchase_rate'],
            ];
            $remaining -= $take;
        }

        if ($remaining > 0.000001) {
            throw new DomainException('Insufficient valid stock');
        }

        return $allocations;
    }
}
