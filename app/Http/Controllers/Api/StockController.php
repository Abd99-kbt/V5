<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Stock::with(['product', 'warehouse']);

        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by product
        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by low stock
        if ($request->has('low_stock') && $request->low_stock) {
            $query->whereRaw('quantity <= (
                SELECT min_stock_level
                FROM products
                WHERE products.id = stocks.product_id
            )');
        }

        // Filter by expiring soon
        if ($request->has('expiring_soon') && $request->expiring_soon) {
            $query->whereNotNull('expiry_date')
                  ->where('expiry_date', '<=', now()->addDays(30));
        }

        $perPage = $request->get('per_page', 15);
        $stocks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المخزون بنجاح',
            'data' => $stocks->items(),
            'pagination' => [
                'current_page' => $stocks->currentPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
                'last_page' => $stocks->lastPage(),
                'from' => $stocks->firstItem(),
                'to' => $stocks->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date|after:today',
            'batch_number' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        // Check if stock already exists for this product in this warehouse
        $existingStock = Stock::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('batch_number', $validated['batch_number'])
            ->first();

        if ($existingStock) {
            // Update existing stock
            $existingStock->addStock($validated['quantity'], $validated['unit_cost']);
            $stock = $existingStock;
            $message = 'تم تحديث المخزون بنجاح';
        } else {
            // Create new stock entry
            $validated['reserved_quantity'] = 0;
            $validated['is_active'] = true;
            $stock = Stock::create($validated);
            $message = 'تم إنشاء المخزون بنجاح';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $stock->load(['product', 'warehouse']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock): JsonResponse
    {
        $stock->load(['product', 'warehouse']);

        return response()->json([
            'success' => true,
            'data' => $stock,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Stock $stock): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date|after:today',
            'batch_number' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : $stock->is_active;

        $stock->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المخزون بنجاح',
            'data' => $stock->load(['product', 'warehouse']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stock $stock): JsonResponse
    {
        if ($stock->reserved_quantity > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete stock with reserved quantity',
            ], Response::HTTP_CONFLICT);
        }

        $stock->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المخزون بنجاح',
        ]);
    }

    /**
     * Adjust stock quantity
     */
    public function adjust(Request $request, Stock $stock): JsonResponse
    {
        $validated = $request->validate([
            'adjustment_type' => 'required|in:add,remove,set',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $success = false;
        $message = '';

        switch ($validated['adjustment_type']) {
            case 'add':
                $success = $stock->addStock($validated['quantity']);
                $message = 'Stock increased successfully';
                break;
            case 'remove':
                $success = $stock->removeStock($validated['quantity']);
                $message = 'Stock decreased successfully';
                break;
            case 'set':
                $stock->quantity = $validated['quantity'];
                $success = $stock->save();
                $message = 'Stock quantity set successfully';
                break;
        }

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock for this operation',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $stock->fresh()->load(['product', 'warehouse']),
        ]);
    }

    /**
     * Reserve stock for orders
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $stock = Stock::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('is_active', true)
            ->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not found for this product and warehouse',
            ], Response::HTTP_NOT_FOUND);
        }

        $success = $stock->reserve($validated['quantity']);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available stock for reservation',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حجز المخزون بنجاح',
            'data' => $stock->fresh()->load(['product', 'warehouse']),
        ]);
    }

    /**
     * Release reserved stock
     */
    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $stock = Stock::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('is_active', true)
            ->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not found for this product and warehouse',
            ], Response::HTTP_NOT_FOUND);
        }

        $success = $stock->release($validated['quantity']);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient reserved stock to release',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock released successfully',
            'data' => $stock->fresh()->load(['product', 'warehouse']),
        ]);
    }

    /**
     * Get stock summary by warehouse
     */
    public function summary(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');

        $query = Stock::with(['product', 'warehouse'])
            ->where('is_active', true);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->get();

        $summary = [
            'total_products' => $stocks->count(),
            'total_quantity' => $stocks->sum('quantity'),
            'total_value' => $stocks->sum('total_value'),
            'low_stock_items' => $stocks->filter(function ($stock) {
                return $stock->quantity <= $stock->product->min_stock_level;
            })->count(),
            'expiring_soon_items' => $stocks->filter(function ($stock) {
                return $stock->isExpiringSoon();
            })->count(),
            'expired_items' => $stocks->filter(function ($stock) {
                return $stock->isExpired();
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
