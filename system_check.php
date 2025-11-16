<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{Order, User, Product, Warehouse};

echo "🧹 تحقق من النظام بعد التنظيف:\n";
try {
    echo "✅ الطلبات: " . Order::count() . " طلب\n";
    echo "✅ المنتجات: " . Product::count() . " منتج\n";  
    echo "✅ المستودعات: " . Warehouse::count() . " مستودع\n";
    echo "✅ المستخدمون: " . User::count() . " مستخدم\n";
    
    // تحقق من طلب 125
    $order125 = Order::where('order_number', '125')->first();
    if ($order125) {
        echo "✅ طلب 125 موجود - الحالة: " . $order125->status . "\n";
    }
    
    echo "\n🎉 النظام يعمل بشكل طبيعي بعد التنظيف!\n";
    echo "🌐 رابط النظام: http://localhost:8000\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}