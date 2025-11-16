<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProcessing;
use App\Services\OrderProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SortingController extends Controller
{
    protected OrderProcessingService $orderProcessingService;

    public function __construct(OrderProcessingService $orderProcessingService)
    {
        $this->orderProcessingService = $orderProcessingService;
    }

    /**
     * Perform sorting operation
     */
    public function performSorting(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'sorting_data' => 'required|array',
            'sorting_data.*.order_material_id' => 'required|exists:order_materials,id',
            'sorting_data.*.original_weight' => 'required|numeric|min:0',
            'sorting_data.*.original_width' => 'required|numeric|min:0',
            'sorting_data.*.roll1_weight' => 'required|numeric|min:0',
            'sorting_data.*.roll1_width' => 'required|numeric|min:0',
            'sorting_data.*.roll1_location' => 'nullable|string',
            'sorting_data.*.roll2_weight' => 'required|numeric|min:0',
            'sorting_data.*.roll2_width' => 'required|numeric|min:0',
            'sorting_data.*.roll2_location' => 'nullable|string',
            'sorting_data.*.waste_weight' => 'required|numeric|min:0',
            'sorting_data.*.waste_reason' => 'nullable|string',
            'sorting_data.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->orderProcessingService->performSorting(
            $order,
            $request->user(),
            $request->sorting_data
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Approve sorting results
     */
    public function approveSorting(Request $request, OrderProcessing $processing)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->orderProcessingService->approveSorting(
            $processing,
            $request->user(),
            $request->notes
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Transfer sorted materials to destination
     */
    public function transferToDestination(Request $request, OrderProcessing $processing)
    {
        $validator = Validator::make($request->all(), [
            'destination_type' => 'required|in:cutting_warehouse,direct_delivery,other_warehouse',
            'destination_warehouse_id' => 'required_if:destination_type,other_warehouse|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->orderProcessingService->transferToDestination(
            $processing,
            $request->user(),
            $request->destination_warehouse_id,
            $request->destination_type
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get sorting summary for an order
     */
    public function getSortingSummary(Order $order)
    {
        $summary = $this->orderProcessingService->getSortingSummary($order);

        if (isset($summary['error'])) {
            return response()->json([
                'success' => false,
                'error' => $summary['error']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get pending sorting approvals for current user
     */
    public function getPendingApprovals(Request $request)
    {
        $approvals = $this->orderProcessingService->getPendingSortingApprovals(
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $approvals
        ]);
    }

    /**
     * Validate sorting data before submission
     */
    public function validateSortingData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sorting_data' => 'required|array',
            'sorting_data.*.order_material_id' => 'required|exists:order_materials,id',
            'sorting_data.*.original_weight' => 'required|numeric|min:0',
            'sorting_data.*.original_width' => 'required|numeric|min:0',
            'sorting_data.*.roll1_weight' => 'required|numeric|min:0',
            'sorting_data.*.roll1_width' => 'required|numeric|min:0',
            'sorting_data.*.roll2_weight' => 'required|numeric|min:0',
            'sorting_data.*.roll2_width' => 'required|numeric|min:0',
            'sorting_data.*.waste_weight' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional business logic validation
        $sortingService = app(\App\Services\SortingService::class);
        $validationErrors = $sortingService->validateSortingData($request->sorting_data);

        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'errors' => $validationErrors
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sorting data is valid'
        ]);
    }
}
