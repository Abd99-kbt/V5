<?php

namespace Tests\Feature;

use App\Models\CuttingResult;
use App\Models\Order;
use App\Models\OrderMaterial;
use App\Models\OrderProcessing;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeightTransfer;
use App\Models\WeightTransferApproval;
use App\Models\InventoryRequest;
use App\Services\OrderProcessingService;
use App\Services\WeightTransferApprovalService;
use App\Services\InventoryRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class CuttingStageScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected OrderProcessingService $orderProcessingService;
    protected WeightTransferApprovalService $approvalService;
    protected InventoryRequestService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProcessingService = app(OrderProcessingService::class);
        $this->approvalService = app(WeightTransferApprovalService::class);
        $this->inventoryService = app(InventoryRequestService::class);
    }

    /**
     * Comprehensive test for the cutting stage scenario
     * Tests the complete workflow: cutting result recording → transfer creation → inventory requests → sequential approvals → stock updates
     * Validates the scenario: 1300kg roll loss, cut plates gain, waste tracking
     */
    public function test_complete_cutting_stage_scenario(): void
    {
        // Setup test data
        $cuttingManager = User::factory()->create(['name' => 'Cutting Manager']);
        $deliveryManager = User::factory()->create(['name' => 'Delivery Manager']);
        $packagingManager = User::factory()->create(['name' => 'Packaging Manager']);

        // Assign roles
        $cuttingManager->assignRole('مسؤول_قصاصة');
        $deliveryManager->assignRole('مسؤول_تسليم');
        $packagingManager->assignRole('أمين_مستودع');

        // Create warehouses
        $cuttingWarehouse = Warehouse::factory()->create([
            'name' => 'Cutting Warehouse',
            'type' => 'مستودع_قصاصة'
        ]);
        $packagingWarehouse = Warehouse::factory()->create([
            'name' => 'Packaging Warehouse',
            'type' => 'مستودع_تعبئة'
        ]);

        // Assign warehouse assignments
        $cuttingManager->warehouseAssignments()->create(['warehouse_id' => $cuttingWarehouse->id]);
        $packagingManager->warehouseAssignments()->create(['warehouse_id' => $packagingWarehouse->id]);

        // Create order and processing
        $order = Order::create([
            'order_number' => 'TEST_ORDER_' . now()->format('Ymd_His'),
            'type' => 'out',
            'status' => 'processing',
            'current_stage' => 'قص',
            'warehouse_id' => $cuttingWarehouse->id,
            'required_weight' => 1300.00,
            'required_length' => 1000,
            'required_width' => 50,
        ]);

        $orderMaterial = OrderMaterial::create([
            'order_id' => $order->id,
            'material_id' => 1, // Assuming material ID 1 exists
            'requested_weight' => 1300.00,
            'required_width' => 50,
            'required_length' => 1000,
            'required_grammage' => 100,
            'quality_grade' => 'A',
            'status' => 'مستخرج',
            'roll_number' => 'CUTTING_TEST_ROLL_001',
            'actual_width' => 60,
            'actual_length' => 1000,
            'actual_grammage' => 100,
        ]);

        // Create a material relationship for the order material
        $orderMaterial->material = (object) [
            'id' => 1,
            'name' => 'Test Material',
            'weight_per_unit' => 1.0,
            'specifications' => ['grammage' => 100, 'quality' => 'A']
        ];

        $orderProcessing = OrderProcessing::factory()->create([
            'order_id' => $order->id,
            'work_stage_id' => 5, // Cutting stage
            'status' => 'in_progress',
            'assigned_to' => $cuttingManager->id,
            'to_warehouse_id' => $cuttingWarehouse->id
        ]);

        // Create initial stock in cutting warehouse (1300kg roll)
        $cuttingStock = \App\Models\Stock::create([
            'product_id' => $orderMaterial->material->id ?? 1,
            'warehouse_id' => $cuttingWarehouse->id,
            'quantity' => 1300.00,
            'reserved_quantity' => 0,
            'unit_cost' => 10.00,
            'is_active' => true,
        ]);

        // Step 1: Record cutting results
        $this->recordCuttingResults($orderProcessing, $cuttingManager, $orderMaterial);

        // Step 2: Approve cutting results and create transfers
        $this->approveCuttingResultsAndCreateTransfers($orderProcessing, $cuttingManager);

        // Step 3: Verify transfer creation and inventory requests
        $this->verifyTransferCreationAndInventoryRequests();

        // Step 4: Execute sequential approvals
        $this->executeSequentialApprovals($cuttingManager, $deliveryManager, $packagingManager);

        // Step 5: Complete transfers and update stock
        $this->completeTransfersAndUpdateStock();

        // Step 6: Validate final scenario
        $this->validateFinalScenario($cuttingWarehouse, $packagingWarehouse, $cuttingStock);
    }

    /**
     * Step 1: Record cutting results
     */
    private function recordCuttingResults(OrderProcessing $processing, User $user, OrderMaterial $orderMaterial): void
    {
        $cuttingData = [
            $orderMaterial->id => [
                'input_weight' => 1300.00,
                'cut_weight' => 1200.00, // Cut plates
                'waste_weight' => 80.00,  // Waste
                'remaining_weight' => 20.00, // Remainder
                'required_length' => 1000,
                'required_width' => 50,
                'actual_cut_length' => 1000,
                'actual_cut_width' => 50,
                'roll_number' => 'CUTTING_TEST_ROLL_001',
                'material_width' => 60,
                'material_grammage' => 100,
                'quality_grade' => 'A',
                'batch_number' => 'BATCH_001',
                'pieces_cut' => 50,
                'cutting_notes' => 'Test cutting operation for 1300kg roll',
                'cutting_machine' => 'Machine_1',
                'operator_name' => 'Test Operator',
                'quality_passed' => true,
                'quality_notes' => 'Quality check passed',
                'quality_measurements' => [
                    'thickness' => 1.0,
                    'width_accuracy' => 1.0,
                    'length_accuracy' => 1.0,
                ],
            ]
        ];

        $result = $this->orderProcessingService->recordCuttingResults($processing, $user, $cuttingData);

        $this->assertTrue($result['success'], 'Cutting results recording failed');
        $this->assertCount(1, $result['results'], 'Expected 1 cutting result');

        $cuttingResult = CuttingResult::first();
        $this->assertNotNull($cuttingResult, 'Cutting result not created');
        $this->assertEquals('completed', $cuttingResult->status, 'Cutting result should be completed');
        $this->assertEquals(1300.00, $cuttingResult->input_weight, 'Input weight mismatch');
        $this->assertEquals(1200.00, $cuttingResult->cut_weight, 'Cut weight mismatch');
        $this->assertEquals(80.00, $cuttingResult->waste_weight, 'Waste weight mismatch');
        $this->assertEquals(20.00, $cuttingResult->remaining_weight, 'Remaining weight mismatch');

        // Validate weight balance
        $balance = $cuttingResult->getWeightBalance();
        $this->assertTrue($balance['is_balanced'], 'Weight balance check failed');
        $this->assertEquals(92.31, round($balance['yield_percentage'], 2), 'Yield percentage mismatch');
    }

    /**
     * Step 2: Approve cutting results and create transfers
     */
    private function approveCuttingResultsAndCreateTransfers(OrderProcessing $processing, User $user): void
    {
        $result = $this->orderProcessingService->approveCuttingResults($processing, $user, 'Approved for cutting workflow test');

        $this->assertTrue($result['success'], 'Cutting results approval failed');
        $this->assertEquals(1, $result['approved_results'], 'Expected 1 approved result');
        $this->assertTrue($result['transfers_created'], 'Transfers should be created');

        $cuttingResult = CuttingResult::first();
        $this->assertEquals('approved', $cuttingResult->status, 'Cutting result should be approved');
        $this->assertNotNull($cuttingResult->approved_at, 'Approved timestamp should be set');
        $this->assertEquals($user->id, $cuttingResult->approved_by, 'Approved by user mismatch');
        $this->assertTrue($cuttingResult->transfers_created, 'Transfers created flag should be true');
    }

    /**
     * Step 3: Verify transfer creation and inventory requests
     */
    private function verifyTransferCreationAndInventoryRequests(): void
    {
        $cuttingResult = CuttingResult::first();

        // Check that transfers were created
        $transfers = WeightTransfer::where('cutting_result_id', $cuttingResult->id)->get();
        $this->assertGreaterThan(0, $transfers->count(), 'No transfers created for cutting result');

        // Check for cut material transfer
        $cutTransfer = $transfers->where('transfer_category', 'cut_material')->first();
        $this->assertNotNull($cutTransfer, 'Cut material transfer not found');
        $this->assertEquals(1200.00, $cutTransfer->weight_transferred, 'Cut material weight mismatch');
        $this->assertEquals('pending', $cutTransfer->status, 'Cut material transfer should be pending');
        $this->assertTrue($cutTransfer->requires_sequential_approval, 'Should require sequential approval');

        // Check for waste transfer (auto-approved)
        $wasteTransfer = $transfers->where('transfer_category', 'cutting_waste')->first();
        $this->assertNotNull($wasteTransfer, 'Waste transfer not found');
        $this->assertEquals(80.00, $wasteTransfer->weight_transferred, 'Waste weight mismatch');
        $this->assertEquals('approved', $wasteTransfer->status, 'Waste transfer should be auto-approved');

        // Check for remainder transfer (auto-approved)
        $remainderTransfer = $transfers->where('transfer_category', 'cutting_remainder')->first();
        $this->assertNotNull($remainderTransfer, 'Remainder transfer not found');
        $this->assertEquals(20.00, $remainderTransfer->weight_transferred, 'Remainder weight mismatch');
        $this->assertEquals('approved', $remainderTransfer->status, 'Remainder transfer should be auto-approved');

        // Verify inventory requests were created
        $inventoryRequests = InventoryRequest::where('weight_transfer_id', $cutTransfer->id)->get();
        $this->assertCount(2, $inventoryRequests, 'Expected 2 inventory requests for cut material transfer');

        $sourceRequest = $inventoryRequests->where('request_type', 'source_check')->first();
        $this->assertNotNull($sourceRequest, 'Source warehouse inventory request not found');
        $this->assertEquals('pending', $sourceRequest->status, 'Source inventory request should be pending');

        $destRequest = $inventoryRequests->where('request_type', 'destination_check')->first();
        $this->assertNotNull($destRequest, 'Destination warehouse inventory request not found');
        $this->assertEquals('pending', $destRequest->status, 'Destination inventory request should be pending');
    }

    /**
     * Step 4: Execute sequential approvals
     */
    private function executeSequentialApprovals(User $cuttingManager, User $deliveryManager, User $packagingManager): void
    {
        $cutTransfer = WeightTransfer::where('transfer_category', 'cut_material')->first();

        // Get approvals for the transfer
        $approvals = $cutTransfer->approvals()->orderBy('approval_sequence')->get();
        $this->assertCount(3, $approvals, 'Expected 3 sequential approvals');

        // Level 1: Cutting warehouse manager approval
        $cuttingApproval = $approvals->where('approval_level', 'cutting_warehouse_manager')->first();
        $this->assertNotNull($cuttingApproval, 'Cutting warehouse manager approval not found');
        $this->assertEquals($cuttingManager->id, $cuttingApproval->approver_id, 'Wrong approver assigned');

        $result = $this->approvalService->approveTransfer($cuttingManager, $cutTransfer, 'Approved by cutting manager');
        $this->assertTrue($result['success'], 'Cutting manager approval failed');
        $this->assertEquals('Transfer approved successfully.', $result['message']);

        $cuttingApproval->refresh();
        $this->assertEquals('approved', $cuttingApproval->approval_status, 'Cutting approval should be approved');

        // Level 2: Delivery manager approval
        $deliveryApproval = $approvals->where('approval_level', 'delivery_manager')->first();
        $this->assertNotNull($deliveryApproval, 'Delivery manager approval not found');
        $this->assertEquals($deliveryManager->id, $deliveryApproval->approver_id, 'Wrong delivery approver assigned');

        $result = $this->approvalService->approveTransfer($deliveryManager, $cutTransfer, 'Approved by delivery manager');
        $this->assertTrue($result['success'], 'Delivery manager approval failed');

        $deliveryApproval->refresh();
        $this->assertEquals('approved', $deliveryApproval->approval_status, 'Delivery approval should be approved');

        // Level 3: Packaging warehouse manager approval (final)
        $packagingApproval = $approvals->where('approval_level', 'packaging_warehouse_manager')->first();
        $this->assertNotNull($packagingApproval, 'Packaging warehouse manager approval not found');
        $this->assertEquals($packagingManager->id, $packagingApproval->approver_id, 'Wrong packaging approver assigned');

        $result = $this->approvalService->approveTransfer($packagingManager, $cutTransfer, 'Approved by packaging manager');
        $this->assertTrue($result['success'], 'Packaging manager approval failed');

        $packagingApproval->refresh();
        $this->assertEquals('approved', $packagingApproval->approval_status, 'Packaging approval should be approved');

        // Verify transfer is now fully approved
        $cutTransfer->refresh();
        $this->assertEquals('approved', $cutTransfer->status, 'Transfer should be fully approved');
        $this->assertTrue($cutTransfer->isFullyApproved(), 'Transfer should be fully approved');
    }

    /**
     * Step 5: Complete transfers and update stock
     */
    private function completeTransfersAndUpdateStock(): void
    {
        $cutTransfer = WeightTransfer::where('transfer_category', 'cut_material')->first();

        // Complete the transfer (this triggers stock updates)
        $completed = $cutTransfer->completeTransfer();
        $this->assertTrue($completed, 'Transfer completion failed');

        $cutTransfer->refresh();
        $this->assertEquals('completed', $cutTransfer->status, 'Transfer should be completed');
        $this->assertNotNull($cutTransfer->transferred_at, 'Transfer timestamp should be set');

        // Complete waste and remainder transfers
        $wasteTransfer = WeightTransfer::where('transfer_category', 'cutting_waste')->first();
        $completed = $wasteTransfer->completeTransfer();
        $this->assertTrue($completed, 'Waste transfer completion failed');

        $remainderTransfer = WeightTransfer::where('transfer_category', 'cutting_remainder')->first();
        $completed = $remainderTransfer->completeTransfer();
        $this->assertTrue($completed, 'Remainder transfer completion failed');
    }

    /**
     * Step 6: Validate final scenario
     */
    private function validateFinalScenario(Warehouse $cuttingWarehouse, Warehouse $packagingWarehouse, $cuttingStock): void
    {
        // Verify cutting warehouse stock (1300kg roll consumed)
        $cuttingStock->refresh();
        $this->assertEquals(0.00, $cuttingStock->quantity, 'Cutting warehouse should have 0kg remaining (roll consumed)');

        // Verify packaging warehouse stock (1200kg plates added)
        $packagingStock = \App\Models\Stock::where('warehouse_id', $packagingWarehouse->id)
            ->where('product_id', $cuttingStock->product_id)
            ->first();

        $this->assertNotNull($packagingStock, 'Packaging warehouse stock not found');
        $this->assertEquals(1200.00, $packagingStock->quantity, 'Packaging warehouse should have 1200kg plates');

        // Verify audit logs
        $auditLogs = \App\Models\WeightTransferAuditLog::whereIn('weight_transfer_id',
            WeightTransfer::where('transfer_category', 'cut_material')->pluck('id')
        )->get();

        $this->assertGreaterThan(0, $auditLogs->count(), 'Audit logs should exist');

        // Check for roll consumption log
        $rollConsumptionLog = $auditLogs->where('stock_change_type', 'cutting_roll_consumed')->first();
        $this->assertNotNull($rollConsumptionLog, 'Roll consumption audit log not found');
        $this->assertEquals(-1300.00, $rollConsumptionLog->stock_quantity_change, 'Roll consumption amount mismatch');

        // Check for plates production log
        $platesProductionLog = $auditLogs->where('stock_change_type', 'cutting_plates_produced')->first();
        $this->assertNotNull($platesProductionLog, 'Plates production audit log not found');
        $this->assertEquals(1200.00, $platesProductionLog->stock_quantity_change, 'Plates production amount mismatch');

        // Check for waste generation log
        $wasteGenerationLog = $auditLogs->where('stock_change_type', 'cutting_waste_generated')->first();
        $this->assertNotNull($wasteGenerationLog, 'Waste generation audit log not found');
        $this->assertEquals(80.00, $wasteGenerationLog->stock_quantity_change, 'Waste generation amount mismatch');

        // Verify inventory requests completed
        $inventoryRequests = InventoryRequest::whereIn('weight_transfer_id',
            WeightTransfer::where('transfer_category', 'cut_material')->pluck('id')
        )->get();

        foreach ($inventoryRequests as $request) {
            $this->assertEquals('completed', $request->status, 'Inventory request should be completed');
            $this->assertNotNull($request->completed_at, 'Inventory request completion timestamp should be set');
        }

        // Verify weight balance across the entire process
        $totalInput = 1300.00; // Original roll
        $totalOutput = $packagingStock->quantity + 80.00; // Plates + Waste (remainder is tracked but not in stock)

        $this->assertEquals($totalInput, $totalOutput, 'Total weight balance mismatch: input vs output');
    }

    /**
     * Test error handling scenarios
     */
    public function test_cutting_stage_error_handling(): void
    {
        $user = User::factory()->create();

        // Test insufficient stock scenario
        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);
        $orderProcessing = OrderProcessing::factory()->create([
            'order_id' => $order->id,
            'status' => 'in_progress'
        ]);

        $cuttingWarehouse = Warehouse::factory()->create(['name' => 'Cutting Warehouse']);
        $packagingWarehouse = Warehouse::factory()->create(['name' => 'Packaging Warehouse']);

        // Create insufficient stock (only 500kg available, but need 1300kg)
        $cuttingStock = \App\Models\Stock::create([
            'product_id' => $orderMaterial->material->id ?? 1,
            'warehouse_id' => $cuttingWarehouse->id,
            'quantity' => 500.00,
            'reserved_quantity' => 0,
            'unit_cost' => 10.00,
            'is_active' => true,
        ]);

        // Create cutting result requiring 1300kg
        $cuttingResult = CuttingResult::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'order_processing_id' => $orderProcessing->id,
            'input_weight' => 1300.00,
            'cut_weight' => 1200.00,
            'waste_weight' => 80.00,
            'remaining_weight' => 20.00,
            'roll_number' => 'CUTTING_TEST_ROLL_INSUFFICIENT',
            'performed_by' => $user->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        // Create transfer
        $transfer = WeightTransfer::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'transfer_category' => 'cut_material',
            'source_warehouse_id' => $cuttingWarehouse->id,
            'destination_warehouse_id' => $packagingWarehouse->id,
            'weight_transferred' => 1200.00,
            'cutting_result_id' => $cuttingResult->id,
            'cutting_quality_verified' => true,
            'status' => 'pending',
            'requested_by' => $user->id,
            'requires_sequential_approval' => true,
        ]);

        // Validate transfer data - should fail due to insufficient stock
        $validation = $transfer->validateTransferData();
        $this->assertFalse($validation['is_valid'], 'Transfer validation should fail with insufficient stock');
        $this->assertContains('Insufficient roll stock in cutting warehouse', $validation['errors'], 'Expected insufficient stock error');

        // Test approval failure when inventory requests not completed
        $manager = User::factory()->create();
        $manager->assignRole('cutting_warehouse_manager');
        $manager->warehouseAssignments()->create(['warehouse_id' => $cuttingWarehouse->id]);

        $result = $this->approvalService->approveTransfer($manager, $transfer, 'Test approval');
        $this->assertFalse($result['success'], 'Approval should fail when inventory requests are pending');
        $this->assertEquals('INVENTORY_REQUESTS_PENDING', $result['error_code'], 'Expected inventory requests pending error');
    }

    /**
     * Test workflow validation and edge cases
     */
    public function test_cutting_workflow_validation(): void
    {
        $user = User::factory()->create();

        // Test cutting result validation
        $cuttingResult = new CuttingResult([
            'input_weight' => 100.00,
            'cut_weight' => 60.00,
            'waste_weight' => 50.00, // This will cause imbalance: 60 + 50 + 0 = 110 > 100
            'remaining_weight' => 0.00,
            'roll_number' => 'TEST_ROLL_001',
        ]);

        $validation = $cuttingResult->validateData();
        $this->assertFalse($validation['is_valid'], 'Cutting result validation should fail');
        $this->assertContains('Weight balance check failed: input vs output difference of 10 kg', $validation['errors'], 'Expected weight balance error');

        // Test balanced cutting result
        $cuttingResult = new CuttingResult([
            'input_weight' => 100.00,
            'cut_weight' => 85.00,
            'waste_weight' => 10.00,
            'remaining_weight' => 5.00, // 85 + 10 + 5 = 100
            'roll_number' => 'TEST_ROLL_002',
        ]);

        $validation = $cuttingResult->validateData();
        $this->assertTrue($validation['is_valid'], 'Cutting result validation should pass for balanced weights');

        // Test transfer without cutting result ID
        $transfer = new WeightTransfer([
            'transfer_category' => 'cut_material',
            'weight_transferred' => 95.00,
            'status' => 'pending',
            'requested_by' => $user->id,
            // Missing cutting_result_id
        ]);

        $validation = $transfer->validateTransferData();
        $this->assertFalse($validation['is_valid'], 'Transfer validation should fail without cutting result ID');
        $this->assertContains('Cutting result ID is required for cutting transfers', $validation['errors'], 'Expected cutting result ID error');
    }
}