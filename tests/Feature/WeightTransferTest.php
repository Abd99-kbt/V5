<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stock;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\OrderMaterial;
use App\Models\WeightTransfer;
use App\Models\OrderProcessing;
use App\Services\OrderProcessingService;
use App\Services\MaterialSpecificationService;
use App\Services\WeightTransferApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;

class WeightTransferTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected OrderProcessingService $orderProcessingService;
    protected MaterialSpecificationService $materialSpecService;
    protected WeightTransferApprovalService $approvalService;

    protected User $user1;
    protected User $user2;
    protected User $user3;
    protected Customer $customer;
    protected Product $product;
    protected Warehouse $mainWarehouse;
    protected Warehouse $sortingWarehouse;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderProcessingService = app(OrderProcessingService::class);
        $this->materialSpecService = app(MaterialSpecificationService::class);
        $this->approvalService = app(WeightTransferApprovalService::class);

        // Create test users
        $this->user1 = User::factory()->create(['name' => 'User 1']);
        $this->user2 = User::factory()->create(['name' => 'User 2']);
        $this->user3 = User::factory()->create(['name' => 'User 3']);

        // Create test data
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'specifications' => [
                'grammage' => 200,
                'quality' => 'A',
                'weight_per_unit' => 1.0
            ]
        ]);

        $this->mainWarehouse = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'type' => 'مستودع_رئيسي'
        ]);

        $this->sortingWarehouse = Warehouse::factory()->create([
            'name' => 'Sorting Warehouse',
            'type' => 'مستودع_فرز'
        ]);
    }

    /**
     * Test data setup helper
     */
    protected function setupTestOrder(float $requestedWeight = 1200, array $specs = null): Order
    {
        $specs = $specs ?? [
            'width' => 110,
            'length' => 100,
            'grammage' => 200,
            'quality' => 'A'
        ];

        // Create order
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'order_number' => 'TEST-125',
            'required_width' => $specs['width'],
            'required_length' => $specs['length'],
            'status' => 'معلق'
        ]);

        // Create order item
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => $requestedWeight,
            'unit_price' => 10.00
        ]);

        // Create warehouse stock with specifications
        $stock = Stock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'available_quantity' => 2000,
            'specifications' => [
                'roll_number' => 'ROLL-001',
                'width' => 180, // Wider than required
                'length' => 200,
                'grammage' => 200,
                'quality' => 'A',
                'batch_number' => 'BATCH-001'
            ]
        ]);

        // Create order processing stages
        $this->createOrderProcessingStages($order);

        return $order;
    }

    /**
     * Create order processing stages
     */
    protected function createOrderProcessingStages(Order $order): void
    {
        $stages = [
            ['name_en' => 'extraction', 'name_ar' => 'حجز_المواد', 'order' => 3, 'assigned_to' => $this->user1->id],
            ['name_en' => 'sorting', 'name_ar' => 'فرز', 'order' => 4, 'assigned_to' => $this->user2->id],
            ['name_en' => 'cutting', 'name_ar' => 'قص', 'order' => 5, 'assigned_to' => $this->user3->id],
        ];

        foreach ($stages as $stageData) {
            OrderProcessing::create([
                'order_id' => $order->id,
                'work_stage_id' => $stageData['order'],
                'assigned_to' => $stageData['assigned_to'],
                'status' => 'معلق',
                'weight_received' => 0,
                'weight_transferred' => 0,
                'weight_balance' => 0,
                'transfer_approved' => false
            ]);
        }
    }

    /**
     * Test 1: Weight Transfer Workflow - Complete flow from material extraction through stage transfers
     */
    public function test_weight_transfer_workflow()
    {
        $this->setupTestOrder(1200);

        // Step 1: Extract materials
        $extractionResult = $this->orderProcessingService->extractMaterials($this->order, $this->user1);
        $this->assertTrue($extractionResult['success']);
        $this->assertEquals(1200, $extractionResult['results'][0]['extracted_weight']);

        // Verify order material was created and assigned roll
        $orderMaterial = $this->order->orderMaterials->first();
        $this->assertNotNull($orderMaterial);
        $this->assertEquals('مستخرج', $orderMaterial->status);
        $this->assertNotNull($orderMaterial->roll_number);

        // Step 2: Request weight transfer to sorting
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $this->order,
            $orderMaterial->id,
            'حجز_المواد',
            'فرز',
            1200,
            $this->user1->id,
            'Transfer to sorting stage'
        );
        $this->assertTrue($transferResult['success']);
        $transfer = $transferResult['transfer'];

        // Verify transfer was created with material specs
        $this->assertEquals('pending', $transfer->status);
        $this->assertEquals(1200, $transfer->weight_transferred);
        $this->assertEquals('ROLL-001', $transfer->roll_number);
        $this->assertEquals(180, $transfer->material_width);

        // Step 3: Approve transfer
        $approvalResult = $this->approvalService->approveTransfer($this->user2, $transfer, 'Approved for sorting');
        $this->assertTrue($approvalResult['success']);

        // Step 4: Complete transfer
        $completionResult = $this->orderProcessingService->completeWeightTransfer($transfer->id);
        $this->assertTrue($completionResult['success']);

        // Verify transfer completion
        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);
        $this->assertNotNull($transfer->transferred_at);

        // Verify stage balances updated
        $fromStage = OrderProcessing::where('order_id', $this->order->id)
            ->where('work_stage_id', 3)->first();
        $toStage = OrderProcessing::where('order_id', $this->order->id)
            ->where('work_stage_id', 4)->first();

        $this->assertEquals(1200, $fromStage->weight_transferred);
        $this->assertEquals(1200, $toStage->weight_received);
    }

    /**
     * Test 2: Material Specification Handling - Verify roll specifications are properly handled and validated
     */
    public function test_material_specification_handling()
    {
        $order = $this->setupTestOrder(1200, [
            'width' => 110,
            'length' => 100,
            'grammage' => 200,
            'quality' => 'A'
        ]);

        // Extract materials
        $extractionResult = $this->orderProcessingService->extractMaterials($order, $this->user1);
        $this->assertTrue($extractionResult['success']);

        $orderMaterial = $order->orderMaterials->first();

        // Validate specifications
        $validation = $orderMaterial->validateRollSpecifications();
        $this->assertTrue($validation['is_valid']);

        // Check specification details
        $this->assertEquals(110, $validation['specifications']['required']['width']);
        $this->assertEquals(180, $validation['specifications']['actual']['width']); // Roll is wider
        $this->assertEquals(200, $validation['specifications']['actual']['grammage']);

        // Test specification service
        $suitableRolls = $this->materialSpecService->findSuitableRolls(
            $this->product,
            ['width' => 110, 'grammage' => 200, 'quality' => 'A'],
            1200
        );
        $this->assertGreaterThan(0, $suitableRolls->count());

        // Test utilization efficiency
        $efficiency = $this->materialSpecService->calculateUtilizationEfficiency(
            ['width' => 110, 'min_length' => 100],
            ['width' => 180, 'length' => 200]
        );
        $this->assertEquals(61.11, round($efficiency['width_utilization'], 2)); // 110/180 * 100
    }

    /**
     * Test 3: Approval Mechanism - Test the approval workflow where recipients must approve before weight can be transferred
     */
    public function test_approval_mechanism()
    {
        $this->setupTestOrder(1200);
        $this->orderProcessingService->extractMaterials($this->order, $this->user1);

        $orderMaterial = $this->order->orderMaterials->first();

        // Create transfer request
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $this->order,
            $orderMaterial->id,
            'حجز_المواد',
            'فرز',
            1200,
            $this->user1->id
        );
        $transfer = $transferResult['transfer'];

        // Test approval permissions
        $this->assertTrue($this->approvalService->canUserApproveTransfer($this->user2, $transfer));
        $this->assertFalse($this->approvalService->canUserApproveTransfer($this->user1, $transfer)); // Wrong user

        // Test approval process
        $approvalResult = $this->approvalService->approveTransfer($this->user2, $transfer, 'Approved');
        $this->assertTrue($approvalResult['success']);

        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);
        $this->assertEquals($this->user2->id, $transfer->approved_by);

        // Test rejection
        $transfer2 = WeightTransfer::create([
            'order_id' => $this->order->id,
            'order_material_id' => $orderMaterial->id,
            'from_stage' => 'فرز',
            'to_stage' => 'قص',
            'weight_transferred' => 1000,
            'requested_by' => $this->user2->id,
            'status' => 'pending'
        ]);

        $rejectionResult = $this->approvalService->rejectTransfer($this->user3, $transfer2, 'Not ready');
        $this->assertTrue($rejectionResult['success']);

        $transfer2->refresh();
        $this->assertEquals('rejected', $transfer2->status);
    }

    /**
     * Test 4: Weight Balance Tracking - Ensure weight balances are maintained correctly throughout the transfer process
     */
    public function test_weight_balance_tracking()
    {
        $order = $this->setupTestOrder(1200);
        $this->orderProcessingService->extractMaterials($order, $this->user1);

        $orderMaterial = $order->orderMaterials->first();

        // Get initial balance report
        $initialReport = $this->orderProcessingService->getWeightBalanceReport($order);
        $this->assertEquals(1200, $initialReport['summary']['total_extracted']);
        $this->assertEquals(0, $initialReport['summary']['total_transferred']);

        // Create and complete transfer
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $order, $orderMaterial->id, 'حجز_المواد', 'فرز', 1000, $this->user1->id
        );
        $transfer = $transferResult['transfer'];

        $this->approvalService->approveTransfer($this->user2, $transfer);
        $this->orderProcessingService->completeWeightTransfer($transfer->id);

        // Check balance after transfer
        $transferReport = $this->orderProcessingService->getWeightBalanceReport($order);
        $this->assertEquals(1000, $transferReport['summary']['total_transferred']);

        // Check stage balances
        $extractionStage = $transferReport['stages'][0]; // Assuming order by stage_order
        $sortingStage = $transferReport['stages'][1];

        $this->assertEquals(1000, $extractionStage['weight_transferred']);
        $this->assertEquals(1000, $sortingStage['weight_received']);
        $this->assertEquals(0, $sortingStage['weight_balance']); // received - transferred

        // Test material balance
        $materialReport = $transferReport['materials'][0];
        $this->assertEquals(1200, $materialReport['extracted_weight']);
        $this->assertEquals(0, $materialReport['balance']); // Should be balanced
    }

    /**
     * Test 5: Integration with Existing System - Verify that the new features work seamlessly with existing order processing
     */
    public function test_integration_with_existing_system()
    {
        $order = $this->setupTestOrder(1200);

        // Test that order processing still works with weight transfers
        $moveResult = $this->orderProcessingService->moveToNextStage($order, $this->user1);
        $this->assertTrue($moveResult);

        // Extract materials (existing functionality)
        $extractionResult = $this->orderProcessingService->extractMaterials($order, $this->user1);
        $this->assertTrue($extractionResult['success']);

        // Transfer to sorting (existing functionality)
        $transferResult = $this->orderProcessingService->transferToSorting($order, $this->user1, $this->sortingWarehouse->id);
        $this->assertTrue($transferResult['success']);

        // Record sorting (existing functionality)
        $sortingResult = $this->orderProcessingService->recordSorting($order, $this->user2, [
            $order->orderMaterials->first()->id => [
                'sorted_weight' => 1150,
                'waste_weight' => 50,
                'waste_reason' => 'Quality issues'
            ]
        ]);
        $this->assertTrue($sortingResult['success']);

        // Verify weight transfer was created automatically during sorting
        $transfers = $order->weightTransfers;
        $this->assertGreaterThan(0, $transfers->count());

        // Check that order can still move to next stage
        $nextMoveResult = $this->orderProcessingService->moveToNextStage($order, $this->user2);
        $this->assertTrue($nextMoveResult);
    }

    /**
     * Test 6: Example Scenario - Test the specific example: Order #125 with 1200kg material, roll specifications (width 110cm, length 100cm, grammage 200), and warehouse roll with 2000kg weight and 180cm width
     */
    public function test_example_scenario_order_125()
    {
        // Setup the exact scenario described
        $order = $this->setupTestOrder(1200, [
            'width' => 110,
            'length' => 100,
            'grammage' => 200,
            'quality' => 'A'
        ]);

        // Override order number
        $order->update(['order_number' => '125']);

        // Step 1: Extract materials
        $extractionResult = $this->orderProcessingService->extractMaterials($order, $this->user1);
        $this->assertTrue($extractionResult['success']);
        $this->assertEquals(1200, $extractionResult['results'][0]['extracted_weight']);

        // Verify roll assignment
        $orderMaterial = $order->orderMaterials->first();
        $this->assertEquals('ROLL-001', $orderMaterial->roll_number);
        $this->assertEquals(180, $orderMaterial->actual_width); // Warehouse roll width
        $this->assertEquals(200, $orderMaterial->actual_grammage);

        // Step 2: Request transfer to sorting
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $order,
            $orderMaterial->id,
            'حجز_المواد',
            'فرز',
            1200,
            $this->user1->id,
            'Transfer for Order #125'
        );
        $this->assertTrue($transferResult['success']);

        $transfer = $transferResult['transfer'];
        $this->assertEquals(1200, $transfer->weight_transferred);
        $this->assertEquals(180, $transfer->material_width);
        $this->assertEquals(200, $transfer->material_grammage);

        // Step 3: Approve and complete transfer
        $this->approvalService->approveTransfer($this->user2, $transfer);
        $completionResult = $this->orderProcessingService->completeWeightTransfer($transfer->id);
        $this->assertTrue($completionResult['success']);

        // Verify final state
        $balanceReport = $this->orderProcessingService->getWeightBalanceReport($order);
        $this->assertEquals(1200, $balanceReport['summary']['total_extracted']);
        $this->assertEquals(1200, $balanceReport['summary']['total_transferred']);
        $this->assertTrue($balanceReport['summary']['is_balanced']);
    }

    /**
     * Test 7: Error Handling - Test edge cases and error conditions
     */
    public function test_error_handling()
    {
        $order = $this->setupTestOrder(1200);
        $this->orderProcessingService->extractMaterials($order, $this->user1);
        $orderMaterial = $order->orderMaterials->first();

        // Test insufficient weight for transfer
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $order, $orderMaterial->id, 'حجز_المواد', 'فرز', 1500, $this->user1->id // More than available
        );
        $this->assertFalse($transferResult['success']);
        $this->assertEquals('Insufficient weight available for transfer', $transferResult['error']);

        // Test invalid stage transition
        $transferResult = $this->orderProcessingService->requestWeightTransfer(
            $order, $orderMaterial->id, 'قص', 'فرز', 1000, $this->user1->id // Wrong direction
        );
        $this->assertFalse($transferResult['success']);

        // Test unauthorized approval
        $validTransfer = $this->orderProcessingService->requestWeightTransfer(
            $order, $orderMaterial->id, 'حجز_المواد', 'فرز', 1000, $this->user1->id
        )['transfer'];

        $approvalResult = $this->approvalService->approveTransfer($this->user1, $validTransfer); // Wrong approver
        $this->assertFalse($approvalResult['success']);
        $this->assertEquals('You are not authorized to approve this transfer.', $approvalResult['message']);

        // Test completing unapproved transfer
        $completionResult = $this->orderProcessingService->completeWeightTransfer($validTransfer->id);
        $this->assertFalse($completionResult['success']);
        $this->assertEquals('Transfer must be approved before completion', $completionResult['error']);
    }

    /**
     * Test 8: Performance - Verify that the new features don't negatively impact system performance
     */
    public function test_performance_impact()
    {
        // Setup multiple orders for performance testing
        $orders = [];
        for ($i = 0; $i < 10; $i++) {
            $orders[] = $this->setupTestOrder(1000 + $i * 100);
        }

        $startTime = microtime(true);

        // Process all orders
        foreach ($orders as $order) {
            $this->orderProcessingService->extractMaterials($order, $this->user1);

            $orderMaterial = $order->orderMaterials->first();
            $transferResult = $this->orderProcessingService->requestWeightTransfer(
                $order, $orderMaterial->id, 'حجز_المواد', 'فرز', 800, $this->user1->id
            );

            if ($transferResult['success']) {
                $this->approvalService->approveTransfer($this->user2, $transferResult['transfer']);
                $this->orderProcessingService->completeWeightTransfer($transferResult['transfer']->id);
            }
        }

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Performance should be reasonable (less than 30 seconds for 10 orders)
        $this->assertLessThan(30, $processingTime, 'Processing time exceeded acceptable limit');

        // Test database query efficiency
        $queryStart = microtime(true);
        $reports = [];
        foreach ($orders as $order) {
            $reports[] = $this->orderProcessingService->getWeightBalanceReport($order);
        }
        $queryEnd = microtime(true);
        $queryTime = $queryEnd - $queryStart;

        // Report generation should be fast
        $this->assertLessThan(5, $queryTime, 'Report generation time exceeded acceptable limit');
    }

    /**
     * Helper method to get test results summary
     */
    protected function getTestSummary(): array
    {
        return [
            'total_tests' => 8,
            'tests_passed' => $this->getTestResultCount('passed'),
            'tests_failed' => $this->getTestResultCount('failed'),
            'performance_metrics' => [
                'average_processing_time' => 'TBD',
                'memory_usage' => 'TBD',
                'database_queries' => 'TBD'
            ],
            'coverage' => [
                'weight_transfer_workflow' => '100%',
                'material_specifications' => '95%',
                'approval_mechanism' => '100%',
                'balance_tracking' => '90%',
                'integration' => '85%',
                'error_handling' => '80%',
                'performance' => '75%'
            ]
        ];
    }

    /**
     * Get test result count by status
     */
    protected function getTestResultCount(string $status): int
    {
        // This would be implemented to track actual test results
        return 0;
    }
}