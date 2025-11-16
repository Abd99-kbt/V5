<?php

namespace App\Services;

use App\Models\InventoryRequest;
use App\Models\WeightTransfer;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryRequestService
{
    /**
     * Create inventory requests for a weight transfer
     */
    public function createInventoryRequestsForTransfer(WeightTransfer $transfer): array
    {
        $requests = [];

        try {
            DB::beginTransaction();

            // Create inventory request for source warehouse if it exists
            if ($transfer->source_warehouse_id) {
                $sourceRequest = $this->createInventoryRequest([
                    'weight_transfer_id' => $transfer->id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'requested_by' => $transfer->requested_by,
                    'request_type' => 'source_check',
                    'request_notes' => 'Inventory check required before transfer approval',
                ]);

                if ($sourceRequest) {
                    $requests[] = $sourceRequest;
                }
            }

            // Create inventory request for destination warehouse if it exists and not waste
            if ($transfer->destination_warehouse_id && $transfer->transfer_category !== 'waste') {
                $destRequest = $this->createInventoryRequest([
                    'weight_transfer_id' => $transfer->id,
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'requested_by' => $transfer->requested_by,
                    'request_type' => 'destination_check',
                    'request_notes' => 'Inventory check required before transfer approval',
                ]);

                if ($destRequest) {
                    $requests[] = $destRequest;
                }
            }

            DB::commit();
            return [
                'success' => true,
                'requests' => $requests,
                'message' => 'Inventory requests created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create inventory requests for transfer', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create inventory requests',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a single inventory request
     */
    public function createInventoryRequest(array $data): ?InventoryRequest
    {
        try {
            return InventoryRequest::create([
                'weight_transfer_id' => $data['weight_transfer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_by' => $data['requested_by'],
                'request_type' => $data['request_type'],
                'status' => 'pending',
                'request_notes' => $data['request_notes'] ?? null,
                'requested_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create inventory request', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Complete an inventory request with inventory data
     */
    public function completeInventoryRequest(int $requestId, array $inventoryData, int $userId): array
    {
        try {
            $request = InventoryRequest::findOrFail($requestId);

            if (!$request->isPending()) {
                return [
                    'success' => false,
                    'message' => 'Inventory request is not pending'
                ];
            }

            $completed = $request->complete($inventoryData);

            if ($completed) {
                Log::info('Inventory request completed', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'warehouse_id' => $request->warehouse_id,
                    'inventory_data' => $inventoryData
                ]);

                return [
                    'success' => true,
                    'message' => 'Inventory request completed successfully',
                    'request' => $request
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to complete inventory request'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to complete inventory request', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while completing the request',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel an inventory request
     */
    public function cancelInventoryRequest(int $requestId, int $userId): array
    {
        try {
            $request = InventoryRequest::findOrFail($requestId);

            if (!$request->isPending()) {
                return [
                    'success' => false,
                    'message' => 'Inventory request is not pending'
                ];
            }

            $cancelled = $request->cancel();

            if ($cancelled) {
                Log::info('Inventory request cancelled', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'warehouse_id' => $request->warehouse_id
                ]);

                return [
                    'success' => true,
                    'message' => 'Inventory request cancelled successfully',
                    'request' => $request
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to cancel inventory request'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to cancel inventory request', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while cancelling the request',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory requests for a transfer
     */
    public function getInventoryRequestsForTransfer(int $transferId): array
    {
        $requests = InventoryRequest::with(['warehouse', 'requester'])
            ->byTransfer($transferId)
            ->orderBy('created_at')
            ->get();

        return $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'warehouse_name' => $request->warehouse->name ?? 'Unknown',
                'request_type' => $request->request_type,
                'status' => $request->status,
                'status_color' => $request->status_color,
                'request_notes' => $request->request_notes,
                'inventory_data' => $request->inventory_data,
                'requested_at' => $request->requested_at,
                'completed_at' => $request->completed_at,
                'requester_name' => $request->requester->name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Get pending inventory requests for a warehouse
     */
    public function getPendingRequestsForWarehouse(int $warehouseId): array
    {
        return InventoryRequest::with(['weightTransfer.order', 'requester'])
            ->byWarehouse($warehouseId)
            ->pending()
            ->orderBy('requested_at')
            ->get()
            ->toArray();
    }

    /**
     * Check if all inventory requests for a transfer are completed
     */
    public function areAllRequestsCompletedForTransfer(int $transferId): bool
    {
        $totalRequests = InventoryRequest::byTransfer($transferId)->count();
        $completedRequests = InventoryRequest::byTransfer($transferId)->completed()->count();

        return $totalRequests > 0 && $totalRequests === $completedRequests;
    }

    /**
     * Get inventory summary for a warehouse
     */
    public function getWarehouseInventorySummary(int $warehouseId): array
    {
        $stocks = Stock::with('product')
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->get();

        return $stocks->map(function ($stock) {
            return [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name ?? 'Unknown',
                'quantity' => $stock->quantity,
                'available_quantity' => $stock->available_quantity,
                'reserved_quantity' => $stock->reserved_quantity,
                'unit_cost' => $stock->unit_cost,
            ];
        })->toArray();
    }
}
