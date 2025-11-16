<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['warehouse', 'supplier', 'orderItems.product']);

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->where('order_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('order_date', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'warehouse_id' => 'required|exists:warehouses,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'customer_address' => 'nullable|string',
            'order_date' => 'required|date',
            'required_date' => 'nullable|date|after:order_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate order number
            $orderNumber = $this->generateOrderNumber($validated['type']);

            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'type' => $validated['type'],
                'status' => 'pending',
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_id' => $validated['supplier_id'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_address' => $validated['customer_address'],
                'order_date' => $validated['order_date'],
                'required_date' => $validated['required_date'],
                'notes' => $validated['notes'],
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'shipping_cost' => 0,
                'total_amount' => 0,
            ]);

            // Create order items and handle stock
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $unitPrice = $item['unit_price'];
                $quantity = $item['quantity'];
                $discount = $item['discount'] ?? 0;
                $totalPrice = ($unitPrice * $quantity) - $discount;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'discount' => $discount,
                ]);

                $subtotal += $totalPrice;

                // Handle stock for sales orders
                if ($order->type === 'out') {
                    // Reserve stock for sales orders
                    $stocks = Stock::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $order->warehouse_id)
                        ->where('is_active', true)
                        ->orderBy('expiry_date')
                        ->get();

                    $remainingQuantity = $quantity;
                    foreach ($stocks as $stock) {
                        if ($remainingQuantity <= 0) break;

                        $reserveQuantity = min($remainingQuantity, $stock->available_quantity);
                        if ($reserveQuantity > 0) {
                            $stock->reserve($reserveQuantity);
                            $remainingQuantity -= $reserveQuantity;
                        }
                    }
                }
            }

            // Update order totals
            $order->subtotal = $subtotal;
            $order->total_amount = $subtotal; // Add tax and shipping calculations here
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['warehouse', 'supplier', 'orderItems.product']),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['warehouse', 'supplier', 'orderItems.product']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'notes' => 'nullable|string',
            'tracking_number' => 'nullable|string',
        ]);

        $oldStatus = $order->status;
        $order->update($validated);

        // Handle status changes
        if ($oldStatus !== $validated['status']) {
            if ($validated['status'] === 'cancelled' && $order->type === 'out') {
                // Release reserved stock for cancelled sales orders
                foreach ($order->orderItems as $item) {
                    $stocks = Stock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->where('is_active', true)
                        ->get();

                    foreach ($stocks as $stock) {
                        $stock->release($item->quantity);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load(['warehouse', 'supplier', 'orderItems.product']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete non-pending orders',
            ], Response::HTTP_CONFLICT);
        }

        // Release reserved stock if it's a sales order
        if ($order->type === 'out') {
            foreach ($order->orderItems as $item) {
                $stocks = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('is_active', true)
                    ->get();

                foreach ($stocks as $stock) {
                    $stock->release($item->quantity);
                }
            }
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(string $type): string
    {
        $prefix = $type === 'in' ? 'PO' : 'SO'; // Purchase Order / Sales Order
        $date = now()->format('Ymd');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return $prefix . $date . $random;
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now()->endOfMonth());

        $query = Order::whereBetween('order_date', [$dateFrom, $dateTo]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $orders = $query->get();

        $statistics = [
            'total_orders' => $orders->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'confirmed_orders' => $orders->where('status', 'confirmed')->count(),
            'processing_orders' => $orders->where('status', 'processing')->count(),
            'shipped_orders' => $orders->where('status', 'shipped')->count(),
            'delivered_orders' => $orders->where('status', 'delivered')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'purchase_orders' => $orders->where('type', 'in')->count(),
            'sales_orders' => $orders->where('type', 'out')->count(),
            'total_value' => $orders->sum('total_amount'),
            'paid_orders' => $orders->where('is_paid', true)->count(),
            'unpaid_orders' => $orders->where('is_paid', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}
