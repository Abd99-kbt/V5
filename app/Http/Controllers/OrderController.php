<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Warehouse;
use App\Services\MaterialSelectionService;
use App\Services\PricingService;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    protected MaterialSelectionService $materialSelectionService;
    protected PricingService $pricingService;

    public function __construct(
        MaterialSelectionService $materialSelectionService,
        PricingService $pricingService
    ) {
        $this->materialSelectionService = $materialSelectionService;
        $this->pricingService = $pricingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'warehouse', 'creator', 'assignedUser'])
            ->advancedFilter([
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'stages' => $request->stages,
                'statuses' => $request->statuses,
                'user' => Auth::user(),
                'warehouse_id' => $request->warehouse_id,
                'urgent_only' => $request->boolean('urgent_only'),
            ]);

        // Sorting
        if ($request->sort_by) {
            $direction = $request->sort_direction ?? 'asc';
            $query->orderBy($request->sort_by, $direction);
        } else {
            $query->byPriority();
        }

        $orders = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'orders' => $orders,
            'filters' => [
                'available_stages' => ['إنشاء', 'مراجعة', 'حجز_المواد', 'فرز', 'قص', 'تعبئة', 'فوترة', 'تسليم'],
                'available_statuses' => ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
                'warehouses' => Warehouse::active()->select('id', 'name')->get(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {

        DB::beginTransaction();
        try {
            $orderData = $request->validated();
            $orderData['order_number'] = Order::generateOrderNumber();
            $orderData['status'] = 'مسودة';
            $orderData['current_stage'] = 'إنشاء';
            $orderData['created_by'] = Auth::id();

            $order = Order::create($orderData);

            // Create order items if provided
            if ($request->has('order_items')) {
                $this->createOrderItems($order, $request->input('order_items', []));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'order' => $order->load(['customer', 'supplier', 'warehouse', 'orderItems.product']),
                'message' => 'Order created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        // $this->authorize('view', $order); // Commented out for now

        return response()->json([
            'order' => $order->load([
                'customer',
                'supplier',
                'warehouse',
                'creator',
                'assignedUser',
                'materialSelector',
                'pricingCalculator',
                'orderItems.product',
                'orderProcessings.workStage',
                'stageHistory.actionUser'
            ]),
            'material_availability' => $this->materialSelectionService->getMaterialAvailabilityReport($order),
            'pricing_summary' => $this->pricingService->getPricingSummary($order),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|exists:customers,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'required_date' => 'nullable|date|after:order_date',
            'material_type' => 'nullable|string',
            'required_weight' => 'nullable|numeric|min:0',
            'required_length' => 'nullable|numeric|min:0',
            'required_width' => 'nullable|numeric|min:0',
            'delivery_method' => 'nullable|string',
            'delivery_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_urgent' => 'boolean',
            'specifications' => 'nullable|array',
            'material_requirements' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update($request->all());

        return response()->json([
            'success' => true,
            'order' => $order->fresh(['customer', 'supplier', 'warehouse']),
            'message' => 'Order updated successfully'
        ]);
    }

    /**
     * Select materials for an order
     */
    public function selectMaterials(Request $request, Order $order)
    {
        // $this->authorize('update', $order); // Commented out for now

        $validator = Validator::make($request->all(), [
            'auto_select' => 'boolean',
            'material_requirements' => 'required_if:auto_select,false|array',
            'material_requirements.*.stock_id' => 'required|exists:stocks,id',
            'material_requirements.*.allocated_weight' => 'required|numeric|min:0',
            'material_requirements.*.specifications' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->materialSelectionService->selectMaterialsForOrder(
            $order,
            Auth::user(),
            $request->boolean('auto_select', true)
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Materials selected successfully'
        ]);
    }

    /**
     * Calculate pricing for an order
     */
    public function calculatePricing(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validator = Validator::make($request->all(), [
            'profit_margin_percentage' => 'nullable|numeric|min:0|max:100',
            'labor_rates' => 'nullable|array',
            'labor_rates.*' => 'numeric|min:0',
            'overhead_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pricingConfig = array_filter($request->only([
            'profit_margin_percentage',
            'labor_rates',
            'overhead_rate'
        ]));

        $result = $this->pricingService->calculateOrderPricing($order, Auth::user(), $pricingConfig);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'pricing' => $result,
            'message' => 'Pricing calculated successfully'
        ]);
    }

    /**
     * Reserve selected materials
     */
    public function reserveMaterials(Order $order)
    {
        $this->authorize('update', $order);

        if (!$order->selected_materials) {
            return response()->json([
                'success' => false,
                'error' => 'No materials selected for reservation'
            ], 400);
        }

        $result = $this->materialSelectionService->reserveSelectedMaterials($order, Auth::user());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Materials reserved successfully'
        ]);
    }

    /**
     * Submit order for approval
     */
    public function submitForApproval(Order $order)
    {
        $this->authorize('update', $order);

        if ($order->status !== 'مسودة') {
            return response()->json([
                'success' => false,
                'error' => 'Order is not in draft status'
            ], 400);
        }

        // Validate that materials are selected and pricing is calculated
        if (!$order->selected_materials) {
            return response()->json([
                'success' => false,
                'error' => 'Materials must be selected before submission'
            ], 400);
        }

        if (!$order->pricing_calculated) {
            return response()->json([
                'success' => false,
                'error' => 'Pricing must be calculated before submission'
            ], 400);
        }

        $order->update([
            'status' => 'قيد_المراجعة',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'order' => $order->fresh(),
            'message' => 'Order submitted for approval successfully'
        ]);
    }

    /**
     * Approve order
     */
    public function approve(Order $order)
    {
        $this->authorize('approve', $order);

        if (!$order->canBeApprovedBy(Auth::user())) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to approve this order'
            ], 403);
        }

        $order->update([
            'status' => 'مؤكد',
            'approved_at' => now(),
            'current_stage' => 'مراجعة',
        ]);

        return response()->json([
            'success' => true,
            'order' => $order->fresh(),
            'message' => 'Order approved successfully'
        ]);
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request)
    {
        $query = Order::query();

        // Apply date filter
        if ($request->date_from) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        $stats = [
            'total_orders' => $query->count(),
            'pending_orders' => (clone $query)->where('status', 'قيد_المراجعة')->count(),
            'approved_orders' => (clone $query)->where('status', 'مؤكد')->count(),
            'completed_orders' => (clone $query)->where('status', 'مكتمل')->count(),
            'total_value' => (clone $query)->sum('final_price'),
            'average_order_value' => (clone $query)->avg('final_price'),
            'urgent_orders' => (clone $query)->where('is_urgent', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Create order items from request data
     */
    private function createOrderItems(Order $order, array $orderItemsData): void
    {
        foreach ($orderItemsData as $itemData) {
            $order->orderItems()->create([
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'] ?? 0,
                'total_price' => ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0),
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }
}
