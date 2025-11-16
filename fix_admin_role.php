<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "=== إصلاح صلاحيات المدير ===\n\n";

// البحث عن المستخدم admin
$user = DB::table('users')->where('username', 'admin')->first();

if (!$user) {
    echo "❌ لم يتم العثور على مستخدم admin\n";
    exit(1);
}

echo "✅ تم العثور على المستخدم: {$user->name} (ID: {$user->id})\n";

// إنشاء الدور admin إذا لم يكن موجوداً
$adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
echo "✅ تم إنشاء/العثور على دور admin\n";

// إنشاء صلاحيات أساسية
$permissions = [
    'view admin panel',
    'manage users',
    'manage orders',
    'manage inventory',
    'view reports',
    'manage settings'
];

foreach ($permissions as $permissionName) {
    Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
}

echo "✅ تم إنشاء الصلاحيات الأساسية\n";

// ربط جميع الصلاحيات بالدور admin
$adminRole->syncPermissions($permissions);
echo "✅ تم ربط الصلاحيات بالدور admin\n";

// إعطاء الدور للمستخدم
$userModel = \App\Models\User::find($user->id);
$userModel->assignRole('admin');
echo "✅ تم إعطاء دور admin للمستخدم\n";

// التحقق من الصلاحيات
$userPermissions = $userModel->getAllPermissions()->pluck('name')->toArray();
echo "صلاحيات المستخدم: " . implode(', ', $userPermissions) . "\n";

echo "\n=== تم إصلاح الصلاحيات بنجاح ===\n";
echo "يمكنك الآن تسجيل الدخول بحساب admin\n";