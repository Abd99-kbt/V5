<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'supplier']);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by supplier
        if ($request->has('supplier_id') && $request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by stock status
        if ($request->has('stock_status')) {
            switch ($request->stock_status) {
                case 'low_stock':
                    $query->whereRaw('total_stock <= min_stock_level');
                    break;
                case 'out_of_stock':
                    $query->where('total_stock', '<=', 0);
                    break;
                case 'in_stock':
                    $query->where('total_stock', '>', 0);
                    break;
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات بنجاح',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'metadata' => [
                'total_products' => $products->total(),
                'current_locale' => app()->getLocale(),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'sku' => 'required|string|unique:products',
            'barcode' => 'nullable|string|unique:products',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit' => 'required|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;
        $validated['track_inventory'] = $request->has('track_inventory') ? $request->track_inventory : true;

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المنتج بنجاح',
            'data' => $product->load(['category', 'supplier']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'supplier', 'stocks.warehouse', 'stockAlerts']);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit' => 'required|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : $product->is_active;
        $validated['track_inventory'] = $request->has('track_inventory') ? $request->track_inventory : $product->track_inventory;

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المنتج بنجاح',
            'data' => $product->load(['category', 'supplier']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has stock or orders
        if ($product->stocks()->sum('quantity') > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المنتج لوجود مخزون مرتبط به',
            ], Response::HTTP_CONFLICT);
        }

        if ($product->orderItems()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المنتج لوجود طلبات مرتبطة به',
            ], Response::HTTP_CONFLICT);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Get product stock information
     */
    public function stock(Product $product): JsonResponse
    {
        $product->load(['stocks.warehouse', 'stockAlerts']);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'total_stock' => $product->total_stock,
                'available_stock' => $product->available_stock,
                'is_low_stock' => $product->isLowStock(),
                'is_out_of_stock' => $product->isOutOfStock(),
                'stock_by_warehouse' => $product->stocks->map(function ($stock) {
                    return [
                        'warehouse' => $stock->warehouse,
                        'quantity' => $stock->quantity,
                        'available_quantity' => $stock->available_quantity,
                        'unit_cost' => $stock->unit_cost,
                        'total_value' => $stock->total_value,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get available products for order creation
     */
    public function availableForOrder(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $filters = $request->only(['type', 'quality', 'min_grammage', 'max_grammage']);

        $products = Product::getAvailableForOrder($warehouseId, $filters);

        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'quality' => $product->quality,
                    'grammage' => $product->grammage,
                    'length' => $product->length,
                    'width' => $product->width,
                    'available_stocks' => $product->stocks->map(function ($stock) {
                        return [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_name' => $stock->warehouse->name,
                            'available_quantity' => $stock->available_quantity,
                            'unit_cost' => $stock->unit_cost,
                        ];
                    }),
                    'specifications' => $product->specifications,
                ];
            }),
        ]);
    }

    /**
     * Check if product can be used for order
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'required_weight' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $product = Product::find($validated['product_id']);
        $canUse = $product->canBeUsedForOrder($validated['required_weight'], $validated['warehouse_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'can_use' => $canUse,
                'product' => $product,
                'warehouse_stock' => $validated['warehouse_id']
                    ? $product->getWarehouseStockInfo($validated['warehouse_id'])
                    : null,
            ],
        ]);
    }

    /**
     * Sync products (for real-time synchronization)
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|integer',
            'products.*.name_en' => 'required|string',
            'products.*.name_ar' => 'required|string',
            'products.*.sku' => 'required|string',
            'products.*.purchase_price' => 'required|numeric',
            'products.*.selling_price' => 'required|numeric',
            'products.*.category_id' => 'required|integer',
            'products.*.supplier_id' => 'required|integer',
            'last_sync' => 'required|date',
        ]);

        $updated = 0;
        $created = 0;

        foreach ($validated['products'] as $productData) {
            $product = Product::updateOrCreate(
                ['id' => $productData['id']],
                [
                    'name_en' => $productData['name_en'],
                    'name_ar' => $productData['name_ar'],
                    'sku' => $productData['sku'],
                    'purchase_price' => $productData['purchase_price'],
                    'selling_price' => $productData['selling_price'],
                    'category_id' => $productData['category_id'],
                    'supplier_id' => $productData['supplier_id'],
                ]
            );

            if ($product->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "تمت المزامنة بنجاح: {$created} تم إنشاؤه، {$updated} تم تحديثه",
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'last_sync' => now(),
            ],
        ]);
    }
}
