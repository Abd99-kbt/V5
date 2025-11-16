<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;

try {
    // Find the main warehouse (المستودع الرئيسي 1)
    $warehouse = Warehouse::where('name_ar', 'المستودع الرئيسي 1')->first();

    if (!$warehouse) {
        throw new Exception('Main warehouse not found');
    }

    // Create the product
    $product = Product::create([
        'name_ar' => 'كرتون مقوى',
        'name_en' => 'Cardboard',
        'sku' => 'CRD-ROLL-200-180',
        'type' => 'roll',
        'quality' => 'premium',
        'grammage' => 200,
        'length' => null, // غير محدد (رول)
        'width' => 180,
        'source' => 'مصنع الكرتون الوطني',
        'purchase_invoice_number' => 'INV-2025-001',
        'roll_number' => 'R-12345',
        'purchase_price' => 0, // Not specified
        'selling_price' => 0, // Not specified
        'category_id' => 1, // Use first category
        'is_active' => true,
        'track_inventory' => true,
    ]);

    // Create stock entry in the main warehouse
    $stock = Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2000, // available weight 2000 kg
        'reserved_quantity' => 0,
        'unit_cost' => 0, // Not specified, set to 0
        'is_active' => true,
    ]);

    // Verify the insertion
    $verifyProduct = Product::find($product->id);
    $verifyStock = Stock::where('product_id', $product->id)
                       ->where('warehouse_id', $warehouse->id)
                       ->first();

    if ($verifyProduct && $verifyStock) {
        echo "Material added successfully!\n";
        echo "Product ID: " . $product->id . "\n";
        echo "Stock ID: " . $stock->id . "\n";
        echo "Name: " . $verifyProduct->name_ar . "\n";
        echo "Quality: " . $verifyProduct->quality_label . "\n";
        echo "Available Weight: " . $verifyStock->quantity . " kg\n";
        echo "Length: " . ($verifyProduct->length ?? 'غير محدد (رول)') . "\n";
        echo "Width: " . $verifyProduct->width . " cm\n";
        echo "Type: " . $verifyProduct->type_label . "\n";
        echo "Grammage: " . $verifyProduct->grammage . " g/m²\n";
        echo "Purchase Invoice: " . $verifyProduct->purchase_invoice_number . "\n";
        echo "Quality Grade: A+\n";
        echo "Roll Number: " . $verifyProduct->roll_number . "\n";
        echo "Warehouse: " . $warehouse->name_ar . "\n";
        echo "Source: " . $verifyProduct->source . "\n";
    } else {
        echo "Verification failed!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}