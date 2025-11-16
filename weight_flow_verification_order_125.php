<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Order;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\WeightTransfer;
use App\Models\Stock;
use App\Models\OrderMaterial;
use App\Models\CuttingResult;
use App\Models\Waste;
use App\Models\WeightTransferAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class WeightFlowVerificationOrder125
{
    private $order;
    private $testResults = [];
    private $weightFlow = [];
    private $inventoryChecks = [];

    public function __construct()
    {
        echo "=== WEIGHT FLOW VERIFICATION SYSTEM - ORDER 125 ===\n";
        echo "==================================================\n\n";
    }

    public function runVerification()
    {
        try {
            $this->step1_InitializeOrder();
            $this->step2_TraceWarehouseTransfer();
            $this->step3_VerifySortingStage();
            $this->step4_VerifyCuttingStage();
            $this->step5_VerifyPackagingStage();
            $this->step6_VerifyDeliveryStage();
            $this->step7_CheckWasteTracking();
            $this->step8_ValidateInventoryAccuracy();
            $this->step9_GenerateComprehensiveReport();

            $this->displayResults();

        } catch (Exception $e) {
            echo "❌ VERIFICATION FAILED: " . $e->getMessage() . "\n";
            $this->testResults[] = ['FAILED', 'Verification error', $e->getMessage()];
            $this->displayResults();
        }
    }

    private function step1_InitializeOrder()
    {
        echo "Step 1: Initializing Order 125\n";
        echo "------------------------------\n";

        $this->order = Order::where('order_number', '125')
                           ->orWhere('id', 125)
                           ->first();

        if (!$this->order) {
            throw new Exception("Order 125 not found");
        }

        echo "✓ Order 125 found\n";
        echo "  - ID: {$this->order->id}\n";
        echo "  - Status: {$this->order->status}\n";
        echo "  - Current Stage: {$this->order->current_stage}\n";
        echo "  - Required Weight: {$this->order->required_weight} kg\n";
        echo "  - Delivered Weight: " . ($this->order->delivered_weight ?? 'N/A') . " kg\n\n";

        $this->weightFlow['order'] = [
            'id' => $this->order->id,
            'required_weight' => $this->order->required_weight,
            'delivered_weight' => $this->order->delivered_weight,
            'status' => $this->order->status,
            'current_stage' => $this->order->current_stage
        ];

        $this->testResults[] = ['PASSED', 'Order initialization', "Order 125 loaded successfully"];
    }

    private function step2_TraceWarehouseTransfer()
    {
        echo "Step 2: Tracing Warehouse Transfer\n";
        echo "----------------------------------\n";

        // Find initial warehouse transfer from main to sorting
        $warehouseTransfer = WeightTransfer::where('order_id', $this->order->id)
                                          ->where('from_stage', 'حجز_المواد')
                                          ->where('to_stage', 'فرز')
                                          ->where('status', 'completed')
                                          ->first();

        if (!$warehouseTransfer) {
            echo "⚠ No completed warehouse transfer found, checking pending...\n";
            $warehouseTransfer = WeightTransfer::where('order_id', $this->order->id)
                                              ->where('from_stage', 'حجز_المواد')
                                              ->where('to_stage', 'فرز')
                                              ->first();
        }

        if ($warehouseTransfer) {
            echo "✓ Warehouse transfer found\n";
            echo "  - Transfer ID: {$warehouseTransfer->id}\n";
            echo "  - Weight: {$warehouseTransfer->weight_transferred} kg\n";
            echo "  - Status: {$warehouseTransfer->status}\n";
            $sourceName = $warehouseTransfer->sourceWarehouse ? $warehouseTransfer->sourceWarehouse->name : 'Unknown';
            $destName = $warehouseTransfer->destinationWarehouse ? $warehouseTransfer->destinationWarehouse->name : 'Unknown';
            echo "  - From: {$sourceName} ({$warehouseTransfer->from_stage})\n";
            echo "  - To: {$destName} ({$warehouseTransfer->to_stage})\n";

            $this->weightFlow['warehouse_transfer'] = [
                'id' => $warehouseTransfer->id,
                'weight' => $warehouseTransfer->weight_transferred,
                'status' => $warehouseTransfer->status,
                'source_warehouse' => $warehouseTransfer->source_warehouse_id,
                'destination_warehouse' => $warehouseTransfer->destination_warehouse_id
            ];

            $this->testResults[] = ['PASSED', 'Warehouse transfer trace', "Transfer ID: {$warehouseTransfer->id}, Weight: {$warehouseTransfer->weight_transferred}kg"];
        } else {
            echo "⚠ No warehouse transfer found for order 125\n";
            $this->testResults[] = ['WARNING', 'Warehouse transfer trace', 'No transfer record found'];
        }

        echo "\n";
    }

    private function step3_VerifySortingStage()
    {
        echo "Step 3: Verifying Sorting Stage\n";
        echo "-------------------------------\n";

        $sortingProcessing = $this->order->orderProcessings()
                                        ->whereHas('workStage', function($q) {
                                            $q->where('name_en', 'Sorting');
                                        })
                                        ->first();

        if ($sortingProcessing) {
            echo "✓ Sorting processing found\n";
            echo "  - Status: {$sortingProcessing->status}\n";
            echo "  - Weight Received: {$sortingProcessing->weight_received} kg\n";
            echo "  - Weight Transferred: {$sortingProcessing->weight_transferred} kg\n";
            echo "  - Roll 1: {$sortingProcessing->roll1_weight} kg ({$sortingProcessing->roll1_width} cm)\n";
            echo "  - Roll 2: {$sortingProcessing->roll2_weight} kg ({$sortingProcessing->roll2_width} cm)\n";
            echo "  - Waste: {$sortingProcessing->sorting_waste_weight} kg\n";

            $this->weightFlow['sorting'] = [
                'weight_received' => $sortingProcessing->weight_received,
                'weight_transferred' => $sortingProcessing->weight_transferred,
                'roll1_weight' => $sortingProcessing->roll1_weight,
                'roll2_weight' => $sortingProcessing->roll2_weight,
                'waste_weight' => $sortingProcessing->sorting_waste_weight,
                'status' => $sortingProcessing->status
            ];

            // Check weight balance
            $totalSorted = ($sortingProcessing->roll1_weight ?? 0) +
                          ($sortingProcessing->roll2_weight ?? 0) +
                          ($sortingProcessing->sorting_waste_weight ?? 0);

            $balanceCheck = abs($totalSorted - ($sortingProcessing->weight_received ?? 0)) < 0.01;
            echo "  - Weight Balance: {$totalSorted} kg sorted vs {$sortingProcessing->weight_received} kg received - ";
            echo ($balanceCheck ? "✓ BALANCED" : "✗ IMBALANCED") . "\n";

            $this->testResults[] = [$balanceCheck ? 'PASSED' : 'FAILED', 'Sorting weight balance', "Total: {$totalSorted}kg, Received: {$sortingProcessing->weight_received}kg"];
        } else {
            echo "⚠ No sorting processing found\n";
            $this->testResults[] = ['WARNING', 'Sorting stage verification', 'No sorting processing record'];
        }

        echo "\n";
    }

    private function step4_VerifyCuttingStage()
    {
        echo "Step 4: Verifying Cutting Stage\n";
        echo "-------------------------------\n";

        $cuttingProcessing = $this->order->orderProcessings()
                                        ->whereHas('workStage', function($q) {
                                            $q->where('name_en', 'Cutting');
                                        })
                                        ->first();

        $cuttingResult = CuttingResult::where('order_id', $this->order->id)->first();

        if ($cuttingResult) {
            echo "✓ Cutting result found\n";
            echo "  - Input Weight: {$cuttingResult->input_weight} kg\n";
            echo "  - Cut Weight: {$cuttingResult->cut_weight} kg\n";
            echo "  - Waste Weight: {$cuttingResult->waste_weight} kg\n";
            echo "  - Remaining Weight: {$cuttingResult->remaining_weight} kg\n";
            echo "  - Pieces Cut: {$cuttingResult->pieces_cut}\n";
            echo "  - Status: {$cuttingResult->status}\n";

            $this->weightFlow['cutting'] = [
                'input_weight' => $cuttingResult->input_weight,
                'cut_weight' => $cuttingResult->cut_weight,
                'waste_weight' => $cuttingResult->waste_weight,
                'remaining_weight' => $cuttingResult->remaining_weight,
                'pieces_cut' => $cuttingResult->pieces_cut,
                'status' => $cuttingResult->status
            ];

            // Check weight balance
            $totalOutput = $cuttingResult->cut_weight + $cuttingResult->waste_weight + $cuttingResult->remaining_weight;
            $balanceCheck = abs($totalOutput - $cuttingResult->input_weight) < 0.01;

            echo "  - Weight Balance: {$totalOutput} kg output vs {$cuttingResult->input_weight} kg input - ";
            echo ($balanceCheck ? "✓ BALANCED" : "✗ IMBALANCED") . "\n";

            $this->testResults[] = [$balanceCheck ? 'PASSED' : 'FAILED', 'Cutting weight balance', "Output: {$totalOutput}kg, Input: {$cuttingResult->input_weight}kg"];
        } else {
            echo "⚠ No cutting result found\n";
            $this->testResults[] = ['WARNING', 'Cutting stage verification', 'No cutting result record'];
        }

        echo "\n";
    }

    private function step5_VerifyPackagingStage()
    {
        echo "Step 5: Verifying Packaging Stage\n";
        echo "----------------------------------\n";

        // Find transfers to packaging warehouse
        $packagingTransfers = WeightTransfer::where('order_id', $this->order->id)
                                           ->where('transfer_category', 'cut_material')
                                           ->where('status', 'completed')
                                           ->get();

        if ($packagingTransfers->count() > 0) {
            echo "✓ Packaging transfers found: {$packagingTransfers->count()}\n";

            $totalPackaged = 0;
            foreach ($packagingTransfers as $transfer) {
                echo "  - Transfer ID: {$transfer->id}, Weight: {$transfer->weight_transferred} kg\n";
                $totalPackaged += $transfer->weight_transferred;
            }

            echo "  - Total Packaged: {$totalPackaged} kg\n";

            $this->weightFlow['packaging'] = [
                'transfers' => $packagingTransfers->count(),
                'total_weight' => $totalPackaged
            ];

            $this->testResults[] = ['PASSED', 'Packaging verification', "Total packaged: {$totalPackaged}kg in {$packagingTransfers->count()} transfers"];
        } else {
            echo "⚠ No packaging transfers found\n";
            $this->testResults[] = ['WARNING', 'Packaging verification', 'No packaging transfer records'];
        }

        echo "\n";
    }

    private function step6_VerifyDeliveryStage()
    {
        echo "Step 6: Verifying Delivery Stage\n";
        echo "---------------------------------\n";

        echo "Order Status: {$this->order->status}\n";
        echo "Delivered Weight: " . ($this->order->delivered_weight ?? 'Not set') . " kg\n";
        echo "Required Weight: {$this->order->required_weight} kg\n";

        if ($this->order->status === 'delivered' && $this->order->delivered_weight) {
            $weightMatch = abs($this->order->delivered_weight - $this->order->required_weight) < 0.01;
            echo "Weight Match: " . ($weightMatch ? "✓ MATCHES" : "✗ MISMATCH") . "\n";

            $this->weightFlow['delivery'] = [
                'delivered_weight' => $this->order->delivered_weight,
                'required_weight' => $this->order->required_weight,
                'weight_match' => $weightMatch
            ];

            $this->testResults[] = [$weightMatch ? 'PASSED' : 'FAILED', 'Delivery weight verification', "Delivered: {$this->order->delivered_weight}kg, Required: {$this->order->required_weight}kg"];
        } else {
            echo "⚠ Order not yet delivered or delivered weight not recorded\n";
            $this->testResults[] = ['WARNING', 'Delivery verification', 'Order not delivered or weight not recorded'];
        }

        echo "\n";
    }

    private function step7_CheckWasteTracking()
    {
        echo "Step 7: Checking Waste Tracking\n";
        echo "--------------------------------\n";

        // Check waste transfers
        $wasteTransfers = WeightTransfer::where('order_id', $this->order->id)
                                       ->whereIn('transfer_category', ['waste', 'cutting_waste'])
                                       ->where('status', 'completed')
                                       ->get();

        $totalWasteTransferred = $wasteTransfers->sum('weight_transferred');

        echo "Waste transfers found: {$wasteTransfers->count()}\n";
        echo "Total waste transferred: {$totalWasteTransferred} kg\n";

        foreach ($wasteTransfers as $transfer) {
            echo "  - Transfer ID: {$transfer->id}, Weight: {$transfer->weight_transferred} kg, Category: {$transfer->transfer_category}\n";
        }

        // Check waste audit logs
        try {
            $wasteAuditLogs = WeightTransferAuditLog::whereIn('weight_transfer_id', $wasteTransfers->pluck('id')->toArray())
                                                    ->where('stock_change_type', 'cutting_waste_generated')
                                                    ->get();
        } catch (Exception $e) {
            // If column doesn't exist, skip audit log check
            $wasteAuditLogs = collect();
            echo "  Note: Waste audit logs not available (column missing)\n";
        }

        echo "Waste audit logs: {$wasteAuditLogs->count()}\n";

        $this->weightFlow['waste'] = [
            'transfers' => $wasteTransfers->count(),
            'total_weight' => $totalWasteTransferred,
            'audit_logs' => $wasteAuditLogs->count()
        ];

        $wasteTracked = $wasteTransfers->count() > 0 || $wasteAuditLogs->count() > 0;
        $this->testResults[] = [$wasteTracked ? 'PASSED' : 'WARNING', 'Waste tracking', "Waste tracked: {$wasteTracked}, Total: {$totalWasteTransferred}kg"];

        echo "\n";
    }

    private function step8_ValidateInventoryAccuracy()
    {
        echo "Step 8: Validating Inventory Accuracy\n";
        echo "--------------------------------------\n";

        $warehouses = Warehouse::all();
        $inventoryAccurate = true;

        foreach ($warehouses as $warehouse) {
            $totalStock = $warehouse->stocks()->sum('quantity');
            echo "Warehouse: {$warehouse->name} - Total Stock: {$totalStock} kg\n";

            // Check for negative stock (shouldn't happen)
            $negativeStocks = $warehouse->stocks()->where('quantity', '<', 0)->count();
            if ($negativeStocks > 0) {
                echo "  ⚠ WARNING: {$negativeStocks} stock entries have negative quantities\n";
                $inventoryAccurate = false;
            }

            $this->inventoryChecks[$warehouse->name] = [
                'total_stock' => $totalStock,
                'negative_entries' => $negativeStocks
            ];
        }

        // Check weight conservation across the entire process
        $initialWeight = $this->weightFlow['warehouse_transfer']['weight'] ?? 0;
        $finalWeight = $this->order->delivered_weight ?? 0;
        $wasteWeight = $this->weightFlow['waste']['total_weight'] ?? 0;

        echo "\nWeight Conservation Check:\n";
        echo "  - Initial Transfer: {$initialWeight} kg\n";
        echo "  - Final Delivery: {$finalWeight} kg\n";
        echo "  - Waste Generated: {$wasteWeight} kg\n";

        $conservationCheck = ($finalWeight + $wasteWeight) <= $initialWeight + 0.1; // Allow small tolerance
        echo "  - Conservation: " . ($conservationCheck ? "✓ MAINTAINED" : "✗ VIOLATED") . "\n";

        $this->testResults[] = [$inventoryAccurate ? 'PASSED' : 'FAILED', 'Inventory accuracy', 'All warehouse inventories validated'];
        $this->testResults[] = [$conservationCheck ? 'PASSED' : 'FAILED', 'Weight conservation', "Final: {$finalWeight}kg + Waste: {$wasteWeight}kg <= Initial: {$initialWeight}kg"];

        echo "\n";
    }

    private function step9_GenerateComprehensiveReport()
    {
        echo "Step 9: Generating Comprehensive Report\n";
        echo "----------------------------------------\n";

        $report = [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'weight_flow' => $this->weightFlow,
            'inventory_checks' => $this->inventoryChecks,
            'test_results' => $this->testResults,
            'generated_at' => now()->toISOString(),
            'verification_status' => $this->getOverallStatus()
        ];

        // Save report to file
        $reportFile = 'weight_flow_report_order_125_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "✓ Comprehensive report generated: {$reportFile}\n";

        $this->testResults[] = ['PASSED', 'Report generation', "Report saved to: {$reportFile}"];

        echo "\n";
    }

    private function getOverallStatus()
    {
        $failedTests = collect($this->testResults)->where('0', 'FAILED')->count();
        $warningTests = collect($this->testResults)->where('0', 'WARNING')->count();

        if ($failedTests > 0) {
            return 'FAILED';
        } elseif ($warningTests > 0) {
            return 'WARNING';
        } else {
            return 'PASSED';
        }
    }

    private function displayResults()
    {
        echo "\n\n=== VERIFICATION RESULTS ===\n";
        echo "============================\n\n";

        $passed = 0;
        $failed = 0;
        $warnings = 0;

        foreach ($this->testResults as $result) {
            $status = $result[0];
            $test = $result[1];
            $details = $result[2] ?? '';

            $symbol = match($status) {
                'PASSED' => '✓',
                'FAILED' => '✗',
                'WARNING' => '⚠',
                default => '?'
            };

            echo "{$symbol} {$test}: {$details}\n";

            switch ($status) {
                case 'PASSED': $passed++; break;
                case 'FAILED': $failed++; break;
                case 'WARNING': $warnings++; break;
            }
        }

        echo "\n--- Summary ---\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Warnings: {$warnings}\n";
        echo "Total Tests: " . count($this->testResults) . "\n";

        $successRate = count($this->testResults) > 0 ? round(($passed / count($this->testResults)) * 100, 2) : 0;
        echo "Success Rate: {$successRate}%\n";

        echo "\n--- Weight Flow Summary ---\n";
        if (isset($this->weightFlow['warehouse_transfer'])) {
            echo "Initial Transfer: {$this->weightFlow['warehouse_transfer']['weight']} kg\n";
        }
        if (isset($this->weightFlow['sorting'])) {
            echo "Sorting - Received: {$this->weightFlow['sorting']['weight_received']} kg, Transferred: {$this->weightFlow['sorting']['weight_transferred']} kg\n";
        }
        if (isset($this->weightFlow['cutting'])) {
            echo "Cutting - Input: {$this->weightFlow['cutting']['input_weight']} kg, Output: {$this->weightFlow['cutting']['cut_weight']} kg\n";
        }
        if (isset($this->weightFlow['packaging'])) {
            echo "Packaging - Total: {$this->weightFlow['packaging']['total_weight']} kg\n";
        }
        if (isset($this->weightFlow['delivery'])) {
            echo "Delivery - Final: {$this->weightFlow['delivery']['delivered_weight']} kg\n";
        }

        if ($failed === 0) {
            echo "\n🎉 VERIFICATION COMPLETED SUCCESSFULLY!\n";
            echo "Weight flow and inventory accuracy verified for order 125.\n";
        } else {
            echo "\n⚠️ VERIFICATION COMPLETED WITH ISSUES\n";
            echo "Some checks failed. Please review the results above.\n";
        }
    }
}

// Run the verification
try {
    $verifier = new WeightFlowVerificationOrder125();
    $verifier->runVerification();
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}