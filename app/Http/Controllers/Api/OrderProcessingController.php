<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponseTrait;
use App\Models\Order;
use App\Models\OrderStage;
use App\Services\OrderProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class OrderProcessingController extends Controller
{
    use ApiResponseTrait;

    protected $orderProcessingService;

    public function __construct(OrderProcessingService $orderProcessingService)
    {
        $this->orderProcessingService = $orderProcessingService;
    }

    /**
     * Get order stages
     */
    public function stages(Order $order): JsonResponse
    {
        $stages = $order->stages()->with(['assignedUser', 'approvedBy'])->get();

        return $this->successResponse([
            'order' => $order,
            'stages' => $stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'stage_name' => $stage->stage_name,
                    'stage_order' => $stage->stage_order,
                    'status' => $stage->status,
                    'status_color' => $stage->stage_color,
                    'started_at' => $stage->started_at,
                    'completed_at' => $stage->completed_at,
                    'assigned_to' => $stage->assignedUser,
                    'approved_by' => $stage->approvedBy,
                    'approved_at' => $stage->approved_at,
                    'requires_approval' => $stage->requires_approval,
                    'approval_status' => $stage->approval_status,
                    'weight_input' => $stage->weight_input,
                    'weight_output' => $stage->weight_output,
                    'waste_weight' => $stage->waste_weight,
                    'notes' => $stage->notes,
                ];
            }),
            'current_stage' => $order->current_stage,
            'stage_color' => $order->stage_color,
        ]);
    }

    /**
     * Move order to next stage
     */
    public function moveToNextStage(Request $request, Order $order): JsonResponse
    {
        if (!$order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية لتعديل هذا الطلب', 403);
        }

        $success = $this->orderProcessingService->moveToNextStage($order, $request->user());

        if (!$success) {
            return $this->errorResponse('لا يمكن الانتقال للمرحلة التالية', 400);
        }

        return $this->successResponse([
            'order' => $order->load('stages'),
            'message' => 'تم الانتقال للمرحلة التالية بنجاح'
        ]);
    }

    /**
     * Extract materials from warehouse
     */
    public function extractMaterials(Request $request, Order $order): JsonResponse
    {
        if (!$order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية لتعديل هذا الطلب', 403);
        }

        if ($order->current_stage !== 'حجز_المواد') {
            return $this->errorResponse('الطلب ليس في مرحلة استخراج المواد', 400);
        }

        $result = $this->orderProcessingService->extractMaterials($order, $request->user());

        if (!$result['success']) {
            return $this->errorResponse($result['error'], 400);
        }

        return $this->successResponse([
            'order' => $order->load(['orderMaterials.material', 'stages']),
            'results' => $result['results'],
            'message' => 'تم استخراج المواد بنجاح'
        ]);
    }

    /**
     * Transfer materials to sorting warehouse
     */
    public function transferToSorting(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sorting_warehouse_id' => 'required|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        if (!$order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية لتعديل هذا الطلب', 403);
        }

        if ($order->current_stage !== 'فرز') {
            return $this->errorResponse('الطلب ليس في مرحلة الفرز', 400);
        }

        $result = $this->orderProcessingService->transferToSorting(
            $order,
            $request->user(),
            $request->sorting_warehouse_id
        );

        if (!$result['success']) {
            return $this->errorResponse($result['error'], 400);
        }

        return $this->successResponse([
            'order' => $order->load(['orderMaterials.material', 'stages']),
            'results' => $result['results'],
            'message' => 'تم نقل المواد للفرز بنجاح'
        ]);
    }

    /**
     * Record sorting results
     */
    public function recordSorting(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sorting_data' => 'required|array',
            'sorting_data.*.order_material_id' => 'required|exists:order_materials,id',
            'sorting_data.*.sorted_weight' => 'required|numeric|min:0',
            'sorting_data.*.waste_weight' => 'required|numeric|min:0',
            'sorting_data.*.waste_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        if (!$order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية لتعديل هذا الطلب', 403);
        }

        $result = $this->orderProcessingService->recordSorting(
            $order,
            $request->user(),
            $request->sorting_data
        );

        if (!$result['success']) {
            return $this->errorResponse($result['error'], 400);
        }

        return $this->successResponse([
            'order' => $order->load(['orderMaterials.material', 'stages']),
            'results' => $result['results'],
            'message' => 'تم تسجيل نتائج الفرز بنجاح'
        ]);
    }

    /**
     * Approve stage
     */
    public function approveStage(Request $request, OrderStage $stage): JsonResponse
    {
        if (!$stage->order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية للموافقة على هذا الطلب', 403);
        }

        $success = $this->orderProcessingService->approveStage($stage, $request->user());

        if (!$success) {
            return $this->errorResponse('لا يمكن الموافقة على هذه المرحلة', 400);
        }

        return $this->successResponse([
            'stage' => $stage->load(['assignedUser', 'approvedBy']),
            'message' => 'تمت الموافقة على المرحلة بنجاح'
        ]);
    }

    /**
     * Reject stage
     */
    public function rejectStage(Request $request, OrderStage $stage): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        if (!$stage->order->canBeModifiedBy($request->user())) {
            return $this->errorResponse('ليس لديك صلاحية لرفض هذا الطلب', 403);
        }

        $success = $this->orderProcessingService->rejectStage($stage, $request->user(), $request->reason);

        if (!$success) {
            return $this->errorResponse('لا يمكن رفض هذه المرحلة', 400);
        }

        return $this->successResponse([
            'stage' => $stage->load(['assignedUser', 'approvedBy']),
            'message' => 'تم رفض المرحلة بنجاح'
        ]);
    }

    /**
     * Get weight balance report
     */
    public function weightBalance(Order $order): JsonResponse
    {
        $report = $this->orderProcessingService->getWeightBalanceReport($order);

        return $this->successResponse($report);
    }

    /**
     * Get orders by stage for current user
     */
    public function ordersByStage(Request $request): JsonResponse
    {
        $user = $request->user();
        $stage = $request->get('stage');

        $query = Order::visibleToUser($user);

        if ($stage) {
            $query->where('current_stage', $stage);
        }

        $orders = $query->with(['customer', 'creator', 'assignedUser'])
                        ->byPriority()
                        ->paginate($request->per_page ?? 15);

        // Add stage colors
        $orders->getCollection()->transform(function ($order) {
            $order->stage_color = $order->stage_color;
            return $order;
        });

        return $this->successResponse($orders);
    }

    /**
     * Get pending approvals for current user
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $user = $request->user();

        $stages = OrderStage::where('assigned_to', $user->id)
                           ->where('requires_approval', true)
                           ->where('approval_status', 'معلق')
                           ->with(['order.customer', 'order.creator'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->per_page ?? 15);

        return $this->successResponse($stages);
    }
}