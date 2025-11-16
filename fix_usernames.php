<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== إصلاح أسماء المستخدمين ===\n\n";

// تحديث الحسابات القديمة بأسماء مستخدمين صحيحة
$updates = [
    ['name' => 'مدير شامل', 'email' => 'admin@example.com', 'username' => 'مدير_شامل_قديم'],
    ['name' => 'موظف مبيعات', 'email' => 'sales@example.com', 'username' => 'موظف_مبيعات_قديم'],
    ['name' => 'مدير مبيعات', 'email' => 'sales_manager@example.com', 'username' => 'مدير_مبيعات_قديم'],
    ['name' => 'مسؤول مستودع', 'email' => 'warehouse@example.com', 'username' => 'مسؤول_مستودع_قديم'],
    ['name' => 'موظف مستودع', 'email' => 'warehouse_emp@example.com', 'username' => 'موظف_مستودع_قديم'],
    ['name' => 'مسؤول فرازة', 'email' => 'sorting@example.com', 'username' => 'مسؤول_فرازة_قديم'],
    ['name' => 'مسؤول قصاصة', 'email' => 'cutting@example.com', 'username' => 'مسؤول_قصاصة_قديم'],
    ['name' => 'مسؤول تسليم', 'email' => 'delivery@example.com', 'username' => 'مسؤول_تسليم_قديم'],
];

foreach ($updates as $update) {
    $user = User::where('name', $update['name'])
                ->where('email', $update['email'])
                ->first();

    if ($user) {
        $user->username = $update['username'];
        $user->save();
        echo "✓ تم تحديث: {$update['name']} → اسم المستخدم: {$update['username']}\n";
    } else {
        echo "✗ لم يتم العثور على: {$update['name']}\n";
    }
}

echo "\n=== فحص النتائج ===\n";

$users = User::select('name', 'username', 'email')->get();
foreach ($users as $user) {
    echo "الاسم: {$user->name}\n";
    echo "اسم المستخدم: {$user->username}\n";
    echo "البريد: {$user->email}\n";
    echo "---\n";
}

echo "\n✅ تم إصلاح أسماء المستخدمين بنجاح!\n";
echo "الآن يمكن تسجيل الدخول باستخدام أسماء المستخدمين.\n";

?>