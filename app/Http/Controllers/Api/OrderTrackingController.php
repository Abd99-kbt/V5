<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkStage;
use App\Services\OrderTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderTrackingController extends Controller
{
    protected OrderTrackingService $trackingService;

    public function __construct(OrderTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Get orders with advanced filtering
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'date_from',
            'date_to',
            'stages',
            'statuses',
            'warehouse_id',
            'urgent_only',
            'sort_by',
            'sort_direction'
        ]);

        $orders = $this->trackingService->getFilteredOrders($filters, Auth::user());

        $paginatedOrders = $orders->paginate($request->per_page ?? 15);

        return response()->json([
            'orders' => $paginatedOrders,
            'filters' => [
                'available_stages' => WorkStage::active()->pluck('name_ar', 'id'),
                'available_statuses' => ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            ]
        ]);
    }

    /**
     * Get stage progress for a specific order
     */
    public function getStageProgress(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $progress = $this->trackingService->getStageProgress($order);

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'current_stage' => $order->current_stage,
            'stage_progress' => $progress,
            'stage_history' => $order->stage_history_summary,
        ]);
    }

    /**
     * Move order to next stage
     */
    public function moveToNextStage(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $result = $this->trackingService->moveToNextStage($order, Auth::user());

        if ($result['success']) {
            return response()->json([
                'message' => 'Order moved to next stage successfully',
                'order' => $order->fresh(),
                'stage_progress' => $this->trackingService->getStageProgress($order->fresh()),
            ]);
        }

        return response()->json(['error' => $result['message']], 400);
    }

    /**
     * Skip a stage
     */
    public function skipStage(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $request->validate([
            'work_stage_id' => 'required|exists:work_stages,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->trackingService->skipStage(
            $order,
            $request->work_stage_id,
            Auth::user(),
            $request->reason
        );

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'order' => $order->fresh(),
            ]);
        }

        return response()->json(['error' => $result['message']], 400);
    }

    /**
     * Get stage statistics
     */
    public function getStageStatistics(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to']);

        $statistics = $this->trackingService->getStageStatistics($filters);

        return response()->json($statistics);
    }

    /**
     * Get available stages for current user
     */
    public function getAvailableStages(): JsonResponse
    {
        $stages = $this->trackingService->getAvailableStagesForUser(Auth::user());

        return response()->json([
            'stages' => $stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'name_en' => $stage->name_en,
                    'name_ar' => $stage->name_ar,
                    'color' => $stage->color,
                    'icon' => $stage->icon,
                    'can_skip' => $stage->can_skip,
                    'estimated_duration' => $stage->estimated_duration,
                    'stage_group' => $stage->stage_group,
                ];
            })
        ]);
    }

    /**
     * Get stage efficiency metrics
     */
    public function getStageEfficiency(Request $request): JsonResponse
    {
        $request->validate([
            'work_stage_id' => 'required|exists:work_stages,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateRange = [];
        if ($request->date_from && $request->date_to) {
            $dateRange = [$request->date_from, $request->date_to];
        }

        $metrics = $this->trackingService->getStageEfficiencyMetrics(
            $request->work_stage_id,
            $dateRange
        );

        return response()->json($metrics);
    }

    /**
     * Bulk assign stages
     */
    public function bulkAssignStages(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'work_stage_id' => 'required|exists:work_stages,id',
            'assignee_id' => 'required|exists:users,id',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();
        $assignee = User::find($request->assignee_id);

        $result = $this->trackingService->bulkAssignStages(
            $orders,
            $request->work_stage_id,
            Auth::user(),
            $assignee
        );

        return response()->json([
            'message' => 'Bulk assignment completed',
            'results' => $result,
        ]);
    }
}
