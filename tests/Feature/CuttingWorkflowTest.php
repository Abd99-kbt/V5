<?php

namespace Tests\Feature;

use App\Models\CuttingResult;
use App\Models\Order;
use App\Models\OrderMaterial;
use App\Models\OrderProcessing;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeightTransfer;
use App\Services\OrderProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CuttingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected OrderProcessingService $orderProcessingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProcessingService = app(OrderProcessingService::class);
    }

    /**
     * Test cutting result creation and validation
     */
    public function test_cutting_result_creation_and_validation(): void
    {
        // Create test data
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);
        $orderProcessing = OrderProcessing::factory()->create([
            'order_id' => $order->id,
            'status' => 'in_progress'
        ]);

        $cuttingData = [
            'input_weight' => 100.00,
            'cut_weight' => 95.00,
            'waste_weight' => 3.00,
            'remaining_weight' => 2.00,
            'required_length' => 1000,
            'required_width' => 50,
            'actual_cut_length' => 1000,
            'actual_cut_width' => 50,
            'roll_number' => 'TEST_ROLL_001',
            'material_width' => 60,
            'material_grammage' => 100,
            'quality_grade' => 'A',
            'batch_number' => 'BATCH_001',
            'pieces_cut' => 25,
            'cutting_notes' => 'Test cutting operation',
            'cutting_machine' => 'Machine_1',
            'operator_name' => 'Test Operator',
            'quality_passed' => true,
            'quality_notes' => 'Quality check passed',
            'quality_measurements' => [
                'thickness' => 1.0,
                'width_accuracy' => 1.0,
                'length_accuracy' => 1.0,
            ],
        ];

        // Test cutting result recording
        $result = $this->orderProcessingService->recordCuttingResults(
            $orderProcessing,
            $user,
            [$orderMaterial->id => $cuttingData]
        );

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']);

        $cuttingResult = CuttingResult::first();
        $this->assertNotNull($cuttingResult);
        $this->assertEquals('completed', $cuttingResult->status);
        $this->assertEquals(100.00, $cuttingResult->input_weight);
        $this->assertEquals(95.00, $cuttingResult->cut_weight);
        $this->assertEquals(25, $cuttingResult->pieces_cut);

        // Test weight balance validation
        $balance = $cuttingResult->getWeightBalance();
        $this->assertTrue($balance['is_balanced']);
        $this->assertEquals(95.0, $balance['yield_percentage']);
    }

    /**
     * Test cutting result approval and transfer creation
     */
    public function test_cutting_result_approval_and_transfer_creation(): void
    {
        // Create test data
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);
        $orderProcessing = OrderProcessing::factory()->create([
            'order_id' => $order->id,
            'status' => 'completed'
        ]);

        // Create cutting result
        $cuttingResult = CuttingResult::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'order_processing_id' => $orderProcessing->id,
            'input_weight' => 100.00,
            'cut_weight' => 95.00,
            'waste_weight' => 3.00,
            'remaining_weight' => 2.00,
            'roll_number' => 'TEST_ROLL_001',
            'performed_by' => $user->id,
            'status' => 'completed',
            'cutting_completed_at' => now(),
        ]);

        // Test approval
        $result = $this->orderProcessingService->approveCuttingResults(
            $orderProcessing,
            $user,
            'Approved for testing'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['approved_results']);

        // Refresh cutting result
        $cuttingResult->refresh();
        $this->assertEquals('approved', $cuttingResult->status);
        $this->assertNotNull($cuttingResult->approved_at);
        $this->assertEquals($user->id, $cuttingResult->approved_by);

        // Check that transfers were created
        $transfers = WeightTransfer::where('cutting_result_id', $cuttingResult->id)->get();
        $this->assertGreaterThan(0, $transfers->count());

        // Check for cut material transfer
        $cutTransfer = $transfers->where('transfer_category', 'cut_material')->first();
        $this->assertNotNull($cutTransfer);
        $this->assertEquals(95.00, $cutTransfer->weight_transferred);
        $this->assertEquals('pending', $cutTransfer->status);

        // Check for waste transfer (auto-approved)
        $wasteTransfer = $transfers->where('transfer_category', 'cutting_waste')->first();
        $this->assertNotNull($wasteTransfer);
        $this->assertEquals(3.00, $wasteTransfer->weight_transferred);
        $this->assertEquals('approved', $wasteTransfer->status);
    }

    /**
     * Test cutting workflow integration with weight transfer approval and stock management
     */
    public function test_cutting_workflow_with_transfer_approval_and_stock_management(): void
    {
        // Create test data
        $user = User::factory()->create();
        $cuttingWarehouse = Warehouse::factory()->create(['name' => 'Cutting Warehouse']);
        $packagingWarehouse = Warehouse::factory()->create(['name' => 'Packaging Warehouse']);

        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);
        $orderProcessing = OrderProcessing::factory()->create([
            'order_id' => $order->id,
            'status' => 'completed'
        ]);

        // Create stock in cutting warehouse (1300kg roll)
        $cuttingStock = \App\Models\Stock::create([
            'product_id' => $orderMaterial->material->id ?? 1,
            'warehouse_id' => $cuttingWarehouse->id,
            'quantity' => 1300.00,
            'reserved_quantity' => 0,
            'unit_cost' => 10.00,
            'is_active' => true,
        ]);

        // Create cutting result
        $cuttingResult = CuttingResult::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'order_processing_id' => $orderProcessing->id,
            'input_weight' => 1300.00,
            'cut_weight' => 1200.00, // Plates
            'waste_weight' => 80.00,  // Waste
            'remaining_weight' => 20.00, // Remainder
            'roll_number' => 'CUTTING_TEST_ROLL_001',
            'performed_by' => $user->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
            'cutting_completed_at' => now(),
            'pieces_cut' => 50,
        ]);

        // Create transfer group for cutting
        $transferGroupId = 'cutting_' . $cuttingResult->id . '_' . now()->timestamp;

        // Create cut material transfer (to packaging warehouse)
        $cutTransfer = WeightTransfer::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'transfer_group_id' => $transferGroupId,
            'transfer_category' => 'cut_material',
            'source_warehouse_id' => $cuttingWarehouse->id,
            'destination_warehouse_id' => $packagingWarehouse->id,
            'weight_transferred' => 1200.00,
            'pieces_transferred' => 50,
            'cutting_result_id' => $cuttingResult->id,
            'cutting_quality_verified' => true,
            'status' => 'pending',
            'requested_by' => $user->id,
            'requires_sequential_approval' => true,
        ]);

        // Create waste transfer (auto-approved)
        $wasteTransfer = WeightTransfer::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'transfer_group_id' => $transferGroupId,
            'transfer_category' => 'cutting_waste',
            'source_warehouse_id' => $cuttingWarehouse->id,
            'weight_transferred' => 80.00,
            'cutting_result_id' => $cuttingResult->id,
            'status' => 'approved', // Auto-approved
            'requested_by' => $user->id,
            'approved_by' => 1, // System user
            'approved_at' => now(),
        ]);

        // Test transfer approval service
        $approvalService = app(\App\Services\WeightTransferApprovalService::class);

        // Approve the cut material transfer
        $result = $approvalService->approveTransfer($user, $cutTransfer, 'Approved for cutting workflow test');

        $this->assertTrue($result['success']);
        $this->assertEquals('Transfer approved successfully.', $result['message']);

        // Refresh transfer
        $cutTransfer->refresh();
        $this->assertEquals('approved', $cutTransfer->status);

        // Complete the transfer (this should trigger stock updates)
        $completed = $cutTransfer->completeTransfer();
        $this->assertTrue($completed);

        $cutTransfer->refresh();
        $this->assertEquals('completed', $cutTransfer->status);

        // Verify stock changes
        $cuttingStock->refresh();
        $this->assertEquals(0.00, $cuttingStock->quantity); // 1300kg roll consumed

        // Check packaging warehouse stock
        $packagingStock = \App\Models\Stock::where('warehouse_id', $packagingWarehouse->id)
            ->where('product_id', $orderMaterial->material->id ?? 1)
            ->first();

        $this->assertNotNull($packagingStock);
        $this->assertEquals(1200.00, $packagingStock->quantity); // Plates added

        // Verify audit logs
        $auditLogs = \App\Models\WeightTransferAuditLog::where('weight_transfer_id', $cutTransfer->id)->get();
        $this->assertGreaterThan(0, $auditLogs->count());

        // Check for roll consumption log
        $rollConsumptionLog = $auditLogs->where('stock_change_type', 'cutting_roll_consumed')->first();
        $this->assertNotNull($rollConsumptionLog);
        $this->assertEquals(-1300.00, $rollConsumptionLog->stock_quantity_change);

        // Check for plates production log
        $platesProductionLog = $auditLogs->where('stock_change_type', 'cutting_plates_produced')->first();
        $this->assertNotNull($platesProductionLog);
        $this->assertEquals(1200.00, $platesProductionLog->stock_quantity_change);
    }

    /**
     * Test cutting transfer validation failures
     */
    public function test_cutting_transfer_validation_failures(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);

        // Create cutting result
        $cuttingResult = CuttingResult::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'input_weight' => 100.00,
            'cut_weight' => 95.00,
            'waste_weight' => 3.00,
            'remaining_weight' => 2.00,
            'roll_number' => 'TEST_ROLL_001',
            'performed_by' => $user->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        // Create transfer without cutting result ID
        $transfer = new WeightTransfer([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'transfer_category' => 'cut_material',
            'weight_transferred' => 95.00,
            'status' => 'pending',
            'requested_by' => $user->id,
            // Missing cutting_result_id
        ]);

        $validation = $transfer->validateTransferData();
        $this->assertFalse($validation['is_valid']);
        $this->assertContains('Cutting result ID is required for cutting transfers', $validation['errors']);
    }

    /**
     * Test cutting stock validation
     */
    public function test_cutting_stock_validation(): void
    {
        $user = User::factory()->create();
        $cuttingWarehouse = Warehouse::factory()->create(['name' => 'Cutting Warehouse']);
        $packagingWarehouse = Warehouse::factory()->create(['name' => 'Packaging Warehouse']);

        $order = Order::factory()->create();
        $orderMaterial = OrderMaterial::factory()->create(['order_id' => $order->id]);

        // Create insufficient stock in cutting warehouse
        $cuttingStock = \App\Models\Stock::create([
            'product_id' => $orderMaterial->material->id ?? 1,
            'warehouse_id' => $cuttingWarehouse->id,
            'quantity' => 500.00, // Only 500kg available, but need 1300kg
            'reserved_quantity' => 0,
            'unit_cost' => 10.00,
            'is_active' => true,
        ]);

        // Create cutting result requiring 1300kg
        $cuttingResult = CuttingResult::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterial->id,
            'input_weight' => 1300.00,
            'cut_weight' => 1200.00,
            'waste_weight' => 80.00,
            'remaining_weight' => 20.00,
            'roll_number' => 'CUTTING_TEST_ROLL_002',
            'performed_by' => $user->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        // Create transfer
        $transfer = new WeightTransfer([
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
        ]);

        $validation = $transfer->validateTransferData();
        $this->assertFalse($validation['is_valid']);
        $this->assertContains('Insufficient roll stock in cutting warehouse', $validation['errors']);
    }

    /**
     * Test cutting result validation errors
     */
    public function test_cutting_result_validation_errors(): void
    {
        $cuttingResult = new CuttingResult([
            'input_weight' => 100.00,
            'cut_weight' => 60.00,
            'waste_weight' => 50.00, // This will cause imbalance
            'remaining_weight' => 10.00,
            'roll_number' => 'TEST_ROLL_001',
        ]);

        $validation = $cuttingResult->validateData();

        $this->assertFalse($validation['is_valid']);
        $this->assertContains('Weight balance check failed', $validation['errors']);
    }

    /**
     * Test cutting result model methods
     */
    public function test_cutting_result_model_methods(): void
    {
        $cuttingResult = new CuttingResult([
            'status' => 'pending',
            'quality_passed' => true,
            'approved_at' => null,
        ]);

        $this->assertTrue($cuttingResult->isPending());
        $this->assertFalse($cuttingResult->isCompleted());
        $this->assertFalse($cuttingResult->isApproved());

        // Test approval
        $user = User::factory()->create();
        $approved = $cuttingResult->approve($user->id, 'Test approval');

        $this->assertTrue($approved);
        $this->assertTrue($cuttingResult->isApproved());
        $this->assertNotNull($cuttingResult->approved_at);
        $this->assertEquals($user->id, $cuttingResult->approved_by);
    }

    /**
     * Test cutting workflow error handling
     */
    public function test_cutting_workflow_error_handling(): void
    {
        // Test with invalid data
        $user = User::factory()->create();
        $orderProcessing = OrderProcessing::factory()->create(['status' => 'pending']);

        $result = $this->orderProcessingService->recordCuttingResults(
            $orderProcessing,
            $user,
            [] // Empty data
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
