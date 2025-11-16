<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{Order, Product, Warehouse, User, OrderStage};

echo "=== فحص حالة نظام تتبع الطلبات ===\n\n";

try {
    echo "📊 إحصائيات النظام:\n";
    echo "• الطلبات: " . Order::count() . " طلب\n";
    echo "• المنتجات: " . Product::count() . " منتج\n";
    echo "• المستودعات: " . Warehouse::count() . " مستودع\n";
    echo "• المستخدمون: " . User::count() . " مستخدم\n";
    echo "• مراحل الطلبات: " . OrderStage::count() . " مرحلة\n";

    echo "\n📋 طلب 125 (مثال):\n";
    $order125 = Order::where('order_number', '125')->first();
    if ($order125) {
        echo "• رقم الطلب: " . $order125->order_number . "\n";
        echo "• المرحلة الحالية: " . $order125->current_stage . "\n";
        echo "• الحالة: " . $order125->status . "\n";
        echo "• الوزن المطلوب: " . $order125->required_weight . "كغ\n";
        
        $stages = OrderStage::where('order_id', $order125->id)->orderBy('stage_order')->get();
        echo "• عدد المراحل: " . $stages->count() . " مرحلة\n";
        
        echo "\n🔄 تفاصيل المراحل:\n";
        foreach ($stages as $stage) {
            $icon = match($stage->status) {
                'مكتمل' => '✅',
                'قيد_التنفيذ' => '🔄',
                'معلق' => '⏳',
                default => '❓'
            };
            echo "  " . $icon . " " . $stage->stage_name . " - " . $stage->status . "\n";
        }
    } else {
        echo "❌ لم يتم العثور على طلب 125\n";
    }

    echo "\n👥 المستخدمون المتاحون:\n";
    $users = User::take(5)->get();
    foreach ($users as $user) {
        $roles = $user->getRoleNames()->implode(', ');
        echo "• " . $user->name . " (دور: " . ($roles ?: 'لا يوجد') . ")\n";
    }

    echo "\n🏢 المستودعات:\n";
    $warehouses = Warehouse::all();
    foreach ($warehouses as $warehouse) {
        echo "• " . $warehouse->name_ar . " (نوع: " . $warehouse->type . ")\n";
    }

    echo "\n🎉 النظام جاهز للاستخدام!\n";
    echo "🌐 رابط النظام: http://localhost:8000\n";
    echo "🔑 للدخول: admin/password أو مدير_شامل/password123\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}