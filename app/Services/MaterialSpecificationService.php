<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class MaterialSpecificationService
{
    /**
     * Find suitable rolls in warehouse that match specifications
     */
    public function findSuitableRolls(
        Product $product,
        array $requiredSpecs,
        float $requiredWeight,
        ?Warehouse $warehouse = null
    ): Collection {
        $query = $product->stocks()
            ->where('is_active', true)
            ->where('available_quantity', '>', 0);

        // Filter by warehouse if specified
        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        } else {
            // Default to main warehouse
            $query->whereHas('warehouse', function($q) {
                $q->where('type', 'مستودع_رئيسي');
            });
        }

        // Apply specification filters
        if (isset($requiredSpecs['width']) && $requiredSpecs['width']) {
            $query->where('specifications->width', '>=', $requiredSpecs['width']);
        }

        if (isset($requiredSpecs['grammage']) && $requiredSpecs['grammage']) {
            $query->where('specifications->grammage', $requiredSpecs['grammage']);
        }

        if (isset($requiredSpecs['quality']) && $requiredSpecs['quality']) {
            $query->where('specifications->quality', $requiredSpecs['quality']);
        }

        if (isset($requiredSpecs['min_length']) && $requiredSpecs['min_length']) {
            $query->where('specifications->length', '>=', $requiredSpecs['min_length']);
        }

        // Order by best match (exact width match first, then closest above requirement)
        $query->orderByRaw('ABS(COALESCE(specifications->"$.width", 0) - ?) ASC', [$requiredSpecs['width'] ?? 0])
              ->orderBy('specifications->grammage')
              ->orderBy('available_quantity', 'desc');

        $stocks = $query->get();

        // Filter by total available weight and return suitable stocks
        return $this->filterStocksByTotalWeight($stocks, $requiredWeight);
    }

    /**
     * Filter stocks by ensuring total available weight meets requirement
     */
    private function filterStocksByTotalWeight(Collection $stocks, float $requiredWeight): Collection
    {
        $suitableStocks = collect();
        $totalAvailable = 0;

        foreach ($stocks as $stock) {
            if ($totalAvailable >= $requiredWeight) break;
            $suitableStocks->push($stock);
            $totalAvailable += $stock->available_quantity;
        }

        return $suitableStocks;
    }

    /**
     * Get roll specifications from stock
     */
    public function getRollSpecifications(Stock $stock): array
    {
        $specs = $stock->specifications ?? [];

        return [
            'roll_number' => $specs['roll_number'] ?? null,
            'width' => $specs['width'] ?? null,
            'length' => $specs['length'] ?? null,
            'grammage' => $specs['grammage'] ?? null,
            'quality' => $specs['quality'] ?? null,
            'batch_number' => $specs['batch_number'] ?? null,
            'manufacture_date' => $specs['manufacture_date'] ?? null,
            'supplier' => $specs['supplier'] ?? null,
            'origin' => $specs['origin'] ?? null,
        ];
    }

    /**
     * Validate if roll specifications meet requirements
     */
    public function validateRollSpecifications(array $requiredSpecs, array $actualSpecs): array
    {
        $issues = [];

        // Width validation: actual must be >= required
        if (isset($requiredSpecs['width']) && isset($actualSpecs['width'])) {
            if ($actualSpecs['width'] < $requiredSpecs['width']) {
                $issues[] = "Roll width {$actualSpecs['width']}cm is less than required {$requiredSpecs['width']}cm";
            }
        }

        // Grammage validation: must match exactly
        if (isset($requiredSpecs['grammage']) && isset($actualSpecs['grammage'])) {
            if ($actualSpecs['grammage'] != $requiredSpecs['grammage']) {
                $issues[] = "Roll grammage {$actualSpecs['grammage']}g/m² does not match required {$requiredSpecs['grammage']}g/m²";
            }
        }

        // Length validation: actual must be >= required minimum
        if (isset($requiredSpecs['min_length']) && isset($actualSpecs['length'])) {
            if ($actualSpecs['length'] < $requiredSpecs['min_length']) {
                $issues[] = "Roll length {$actualSpecs['length']}m is less than required minimum {$requiredSpecs['min_length']}m";
            }
        }

        // Quality validation: must match
        if (isset($requiredSpecs['quality']) && isset($actualSpecs['quality'])) {
            if ($actualSpecs['quality'] !== $requiredSpecs['quality']) {
                $issues[] = "Roll quality '{$actualSpecs['quality']}' does not match required '{$requiredSpecs['quality']}'";
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'required' => $requiredSpecs,
            'actual' => $actualSpecs,
        ];
    }

    /**
     * Calculate material utilization efficiency
     */
    public function calculateUtilizationEfficiency(array $requiredSpecs, array $actualSpecs): array
    {
        $efficiency = [
            'width_utilization' => null,
            'length_utilization' => null,
            'overall_efficiency' => null,
        ];

        if (isset($requiredSpecs['width']) && isset($actualSpecs['width']) && $actualSpecs['width'] > 0) {
            $efficiency['width_utilization'] = ($requiredSpecs['width'] / $actualSpecs['width']) * 100;
        }

        if (isset($requiredSpecs['min_length']) && isset($actualSpecs['length']) && $actualSpecs['length'] > 0) {
            $efficiency['length_utilization'] = ($requiredSpecs['min_length'] / $actualSpecs['length']) * 100;
        }

        // Overall efficiency is the product of individual efficiencies
        if ($efficiency['width_utilization'] && $efficiency['length_utilization']) {
            $efficiency['overall_efficiency'] = ($efficiency['width_utilization'] * $efficiency['length_utilization']) / 100;
        }

        return $efficiency;
    }

    /**
     * Find optimal roll combination for order requirements
     */
    public function findOptimalRollCombination(
        Product $product,
        array $requiredSpecs,
        float $requiredWeight,
        ?Warehouse $warehouse = null
    ): array {
        $suitableRolls = $this->findSuitableRolls($product, $requiredSpecs, $requiredWeight, $warehouse);

        if ($suitableRolls->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No suitable rolls found',
                'rolls' => [],
            ];
        }

        // Sort by efficiency (best utilization first)
        $rollsWithEfficiency = $suitableRolls->map(function($stock) use ($requiredSpecs) {
            $actualSpecs = $this->getRollSpecifications($stock);
            $validation = $this->validateRollSpecifications($requiredSpecs, $actualSpecs);
            $efficiency = $this->calculateUtilizationEfficiency($requiredSpecs, $actualSpecs);

            return [
                'stock' => $stock,
                'specifications' => $actualSpecs,
                'validation' => $validation,
                'efficiency' => $efficiency,
                'available_weight' => $stock->available_quantity,
            ];
        })->sortByDesc('efficiency.overall_efficiency');

        // Select optimal combination (greedy approach)
        $selectedRolls = [];
        $remainingWeight = $requiredWeight;

        foreach ($rollsWithEfficiency as $rollData) {
            if ($remainingWeight <= 0) break;

            $weightToTake = min($rollData['available_weight'], $remainingWeight);
            $selectedRolls[] = array_merge($rollData, ['allocated_weight' => $weightToTake]);
            $remainingWeight -= $weightToTake;
        }

        return [
            'success' => $remainingWeight <= 0,
            'rolls' => $selectedRolls,
            'total_allocated' => $requiredWeight - $remainingWeight,
            'remaining_required' => max(0, $remainingWeight),
        ];
    }

    /**
     * Get warehouse inventory summary by specifications
     */
    public function getWarehouseSpecificationSummary(?Warehouse $warehouse = null): array
    {
        $query = Stock::with(['product', 'warehouse'])
            ->where('is_active', true)
            ->where('available_quantity', '>', 0);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $stocks = $query->get();

        $summary = [
            'total_rolls' => $stocks->count(),
            'total_weight' => $stocks->sum('available_quantity'),
            'specifications' => [
                'widths' => [],
                'grammages' => [],
                'qualities' => [],
            ],
            'products' => [],
        ];

        foreach ($stocks as $stock) {
            $specs = $this->getRollSpecifications($stock);

            // Group by specifications
            if ($specs['width']) {
                $summary['specifications']['widths'][$specs['width']] =
                    ($summary['specifications']['widths'][$specs['width']] ?? 0) + $stock->available_quantity;
            }

            if ($specs['grammage']) {
                $summary['specifications']['grammages'][$specs['grammage']] =
                    ($summary['specifications']['grammages'][$specs['grammage']] ?? 0) + $stock->available_quantity;
            }

            if ($specs['quality']) {
                $summary['specifications']['qualities'][$specs['quality']] =
                    ($summary['specifications']['qualities'][$specs['quality']] ?? 0) + $stock->available_quantity;
            }

            // Group by products
            $productName = $stock->product->name ?? 'Unknown';
            if (!isset($summary['products'][$productName])) {
                $summary['products'][$productName] = [
                    'total_weight' => 0,
                    'rolls_count' => 0,
                ];
            }
            $summary['products'][$productName]['total_weight'] += $stock->available_quantity;
            $summary['products'][$productName]['rolls_count']++;
        }

        return $summary;
    }
}