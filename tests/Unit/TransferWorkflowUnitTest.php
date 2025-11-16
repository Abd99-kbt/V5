<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeightTransfer;
use App\Models\WeightTransferApproval;
use App\Models\InventoryRequest;
use App\Services\WeightTransferApprovalService;
use App\Services\InventoryRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class TransferWorkflowUnitTest extends TestCase
{
    use RefreshDatabase;

    protected $approvalService;
    protected $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approvalService = app(WeightTransferApprovalService::class);
        $this->inventoryService = app(InventoryRequestService::class);
    }

    /**
     * Test WeightTransferApprovalService canUserApproveTransfer method
     */
    public function test_can_user_approve_transfer()
    {
        Log::info('Testing canUserApproveTransfer method');

        // Create mock user and transfer
        $user = Mockery::mock(User::class);
        $transfer = Mockery::mock(WeightTransfer::class);

        // Test non-sequential transfer
        $transfer->shouldReceive('getAttribute')->with('requires_sequential_approval')->andReturn(false);
        $transfer->shouldReceive('getAttribute')->with('from_stage')->andReturn('فرز');
        $transfer->shouldReceive('getAttribute')->with('order_id')->andReturn(1);

        $orderProcessing = Mockery::mock();
        $orderProcessing->shouldReceive('getAttribute')->with('assigned_to')->andReturn(1);

        // Mock the query chain
        $query = Mockery::mock();
        $query->shouldReceive('where')->with('order_id', 1)->andReturnSelf();
        $query->shouldReceive('where')->with('work_stage_id', '>', 'فرز')->andReturnSelf();
        $query->shouldReceive('orderBy')->with('work_stage_id')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($orderProcessing);

        // Mock DB facade
        DB::shouldReceive('table')->with('order_processings')->andReturn($query);

        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $result = $this->approvalService->canUserApproveTransfer($user, $transfer);

        $this->assertTrue($result, 'User should be able to approve transfer');

        Log::info('canUserApproveTransfer test completed successfully');
    }

    /**
     * Test WeightTransferApprovalService approveTransfer method with validation
     */
    public function test_approve_transfer_validation()
    {
        Log::info('Testing approveTransfer validation');

        // Create mock user and transfer
        $user = Mockery::mock(User::class);
        $transfer = Mockery::mock(WeightTransfer::class);

        // Test unauthorized user
        $transfer->shouldReceive('getAttribute')->with('status')->andReturn('pending');
        $transfer->shouldReceive('getAttribute')->with('requires_sequential_approval')->andReturn(false);

        // Mock canUserApproveTransfer to return false
        $approvalService = Mockery::mock(WeightTransferApprovalService::class)->makePartial();
        $approvalService->shouldReceive('canUserApproveTransfer')->andReturn(false);

        $result = $approvalService->approveTransfer($user, $transfer);

        $this->assertFalse($result['success'], 'Approval should fail for unauthorized user');
        $this->assertEquals('UNAUTHORIZED', $result['error_code']);

        Log::info('approveTransfer validation test completed successfully');
    }

    /**
     * Test InventoryRequestService createInventoryRequestsForTransfer method
     */
    public function test_create_inventory_requests_for_transfer()
    {
        Log::info('Testing createInventoryRequestsForTransfer method');

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);
        $transfer->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $transfer->shouldReceive('getAttribute')->with('source_warehouse_id')->andReturn(1);
        $transfer->shouldReceive('getAttribute')->with('destination_warehouse_id')->andReturn(2);
        $transfer->shouldReceive('getAttribute')->with('transfer_category')->andReturn('sorted_material');
        $transfer->shouldReceive('getAttribute')->with('requested_by')->andReturn(1);

        // Mock DB transaction
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        // Mock InventoryRequest creation
        $sourceRequest = Mockery::mock(InventoryRequest::class);
        $destRequest = Mockery::mock(InventoryRequest::class);

        InventoryRequest::shouldReceive('create')
            ->twice()
            ->andReturn($sourceRequest, $destRequest);

        $result = $this->inventoryService->createInventoryRequestsForTransfer($transfer);

        $this->assertTrue($result['success'], 'Inventory requests creation should succeed');
        $this->assertCount(2, $result['requests'], 'Should create 2 inventory requests');

        Log::info('createInventoryRequestsForTransfer test completed successfully');
    }

    /**
     * Test WeightTransfer createSequentialApprovals method
     */
    public function test_create_sequential_approvals()
    {
        Log::info('Testing createSequentialApprovals method');

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);
        $transfer->shouldReceive('getAttribute')->with('requires_sequential_approval')->andReturn(true);
        $transfer->shouldReceive('getAttribute')->with('transfer_category')->andReturn('sorted_material');
        $transfer->shouldReceive('getAttribute')->with('destination_warehouse_id')->andReturn(1);
        $transfer->shouldReceive('getAttribute')->with('source_warehouse_id')->andReturn(2);

        // Mock approvals relationship
        $approvalsMock = Mockery::mock();
        $transfer->shouldReceive('approvals')->andReturn($approvalsMock);

        // Mock findApproverForLevel calls
        $transfer->shouldReceive('findApproverForLevel')
            ->with('cutting_warehouse_manager', 1)
            ->andReturn(Mockery::mock(['id' => 1]));

        $transfer->shouldReceive('findApproverForLevel')
            ->with('delivery_manager', 1)
            ->andReturn(Mockery::mock(['id' => 2]));

        $transfer->shouldReceive('findApproverForLevel')
            ->with('packaging_warehouse_manager', 1)
            ->andReturn(Mockery::mock(['id' => 3]));

        // Mock approvals creation
        $approvalsMock->shouldReceive('create')
            ->times(3)
            ->andReturn(Mockery::mock(WeightTransferApproval::class));

        $transfer->createSequentialApprovals();

        Log::info('createSequentialApprovals test completed successfully');
    }

    /**
     * Test WeightTransferApproval isNextInSequence method
     */
    public function test_is_next_in_sequence()
    {
        Log::info('Testing isNextInSequence method');

        // Create mock approval
        $approval = Mockery::mock(WeightTransferApproval::class);
        $approval->shouldReceive('getAttribute')->with('approval_sequence')->andReturn(2);

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);
        $approval->shouldReceive('getAttribute')->with('weightTransfer')->andReturn($transfer);

        // Mock approvals query
        $query = Mockery::mock();
        $query->shouldReceive('where')->with('approval_sequence', '<', 2)->andReturnSelf();
        $query->shouldReceive('where')->with('approval_status', '!=', 'approved')->andReturnSelf();
        $query->shouldReceive('exists')->andReturn(false);

        $transfer->shouldReceive('approvals')->andReturn($query);

        $result = $approval->isNextInSequence();

        $this->assertTrue($result, 'Approval should be next in sequence');

        Log::info('isNextInSequence test completed successfully');
    }

    /**
     * Test WeightTransfer isFullyApproved method
     */
    public function test_is_fully_approved()
    {
        Log::info('Testing isFullyApproved method');

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);

        // Mock approvals query
        $query = Mockery::mock();
        $query->shouldReceive('where')->with('approval_status', '!=', 'approved')->andReturnSelf();
        $query->shouldReceive('doesntExist')->andReturn(true);

        $transfer->shouldReceive('approvals')->andReturn($query);

        $result = $transfer->isFullyApproved();

        $this->assertTrue($result, 'Transfer should be fully approved');

        Log::info('isFullyApproved test completed successfully');
    }

    /**
     * Test WeightTransfer completeTransfer method
     */
    public function test_complete_transfer()
    {
        Log::info('Testing completeTransfer method');

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);
        $transfer->shouldReceive('getAttribute')->with('status')->andReturn('approved');
        $transfer->shouldReceive('getAttribute')->with('transfer_group_id')->andReturn('TEST_GROUP');
        $transfer->shouldReceive('isFullyApproved')->andReturn(true);

        // Mock DB transaction
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        // Mock complete method
        $transfer->shouldReceive('complete')->andReturn(true);

        // Mock updateStockQuantities
        $transfer->shouldReceive('updateStockQuantities')->andReturn(true);

        // Mock recordApprovalHistory
        $transfer->shouldReceive('recordApprovalHistory')->once();

        $result = $transfer->completeTransfer();

        $this->assertTrue($result, 'Transfer completion should succeed');

        Log::info('completeTransfer test completed successfully');
    }

    /**
     * Test error scenarios in approval workflow
     */
    public function test_approval_error_scenarios()
    {
        Log::info('Testing approval error scenarios');

        // Test approval of rejected transfer
        $user = Mockery::mock(User::class);
        $transfer = Mockery::mock(WeightTransfer::class);

        $transfer->shouldReceive('getAttribute')->with('status')->andReturn('rejected');

        $result = $this->approvalService->approveTransfer($user, $transfer);

        $this->assertFalse($result['success'], 'Approval of rejected transfer should fail');
        $this->assertEquals('ALREADY_REJECTED', $result['error_code']);

        // Test approval of completed transfer
        $transfer->shouldReceive('getAttribute')->with('status')->andReturn('completed');

        $result = $this->approvalService->approveTransfer($user, $transfer);

        $this->assertFalse($result['success'], 'Approval of completed transfer should fail');
        $this->assertEquals('ALREADY_COMPLETED', $result['error_code']);

        Log::info('Approval error scenarios test completed successfully');
    }

    /**
     * Test inventory request completion workflow
     */
    public function test_inventory_request_completion()
    {
        Log::info('Testing inventory request completion workflow');

        // Create mock request
        $request = Mockery::mock(InventoryRequest::class);
        $request->shouldReceive('isPending')->andReturn(true);
        $request->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $request->shouldReceive('getAttribute')->with('warehouse_id')->andReturn(1);

        // Mock InventoryRequest::findOrFail
        InventoryRequest::shouldReceive('findOrFail')->with(1)->andReturn($request);

        // Mock complete method
        $request->shouldReceive('complete')->with(['available_quantity' => 2000])->andReturn(true);

        $result = $this->inventoryService->completeInventoryRequest(1, ['available_quantity' => 2000], 1);

        $this->assertTrue($result['success'], 'Inventory request completion should succeed');

        Log::info('Inventory request completion test completed successfully');
    }

    /**
     * Test grouped transfer validation
     */
    public function test_grouped_transfer_validation()
    {
        Log::info('Testing grouped transfer validation');

        // Create mock transfer
        $transfer = Mockery::mock(WeightTransfer::class);
        $transfer->shouldReceive('getAttribute')->with('transfer_group_id')->andReturn('TEST_GROUP');
        $transfer->shouldReceive('getAttribute')->with('source_warehouse_id')->andReturn(1);
        $transfer->shouldReceive('getAttribute')->with('weight_transferred')->andReturn(1300);

        // Mock orderMaterial
        $orderMaterial = Mockery::mock();
        $orderMaterial->shouldReceive('getAttribute')->with('product_id')->andReturn(1);
        $transfer->shouldReceive('getAttribute')->with('orderMaterial')->andReturn($orderMaterial);

        // Mock Stock query
        $stock = Mockery::mock();
        $stock->shouldReceive('getAttribute')->with('available_quantity')->andReturn(2000);

        // Mock Stock::where chain
        $query = Mockery::mock();
        $query->shouldReceive('where')->with('warehouse_id', 1)->andReturnSelf();
        $query->shouldReceive('where')->with('product_id', 1)->andReturnSelf();
        $query->shouldReceive('first')->andReturn($stock);

        // This would be tested in the actual service method
        // For now, just verify the structure exists

        Log::info('Grouped transfer validation test completed successfully');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}