<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "🔐 اختبار نظام المصادقة بالأسماء العربية\n";
echo "==========================================\n\n";

// Check if users exist with Arabic usernames
echo "1. فحص المستخدمين في قاعدة البيانات:\n";
echo "----------------------------------------\n";

try {
    $users = User::all();
    foreach ($users as $user) {
        echo "👤 المستخدم: " . $user->name . "\n";
        echo "   📧 البريد: " . ($user->email ?? 'غير محدد') . "\n";
        echo "   🔖 اسم المستخدم: " . ($user->username ?? 'غير محدد') . "\n";
        echo "   🔒 الدور: " . ($user->getRoleNames()->first() ?? 'غير محدد') . "\n";
        echo "\n";
    }
} catch (Exception $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
}

echo "\n2. اختبار أسماء المستخدمين العربية:\n";
echo "----------------------------------------\n";

$testUsernames = [
    'مدير_شامل' => 'password123',
    'موظف_مبيعات' => 'password123',
    'محاسب' => 'password123',
    'مسؤول_مستودع' => 'password123',
    'مسؤول_فرازة' => 'password123',
    'مسؤول_قصاصة' => 'password123',
    'موظف_مستودع' => 'password123',
    'مسؤول_تسليم' => 'password123',
    'admin' => 'password',
];

foreach ($testUsernames as $username => $password) {
    echo "🧪 اختبار المستخدم: $username\n";
    
    $user = User::where('username', $username)->first();
    
    if ($user) {
        echo "   ✅ تم العثور على المستخدم\n";
        echo "   📝 الاسم: " . $user->name . "\n";
        echo "   📧 البريد: " . ($user->email ?? 'غير محدد') . "\n";
        echo "   🔒 الدور: " . ($user->getRoleNames()->first() ?? 'غير محدد') . "\n";
        
        // Test password verification
        if (password_verify($password, $user->password)) {
            echo "   ✅ كلمة المرور صحيحة\n";
        } else {
            echo "   ❌ كلمة المرور خاطئة\n";
        }
    } else {
        echo "   ❌ لم يتم العثور على المستخدم\n";
    }
    echo "\n";
}

echo "\n3. اختبار قاعدة البيانات:\n";
echo "----------------------------------------\n";

// Test database connection
try {
    $totalUsers = User::count();
    echo "📊 إجمالي المستخدمين: $totalUsers\n";
    
    $usersWithUsernames = User::whereNotNull('username')->count();
    echo "👥 المستخدمين بأسماء مستخدمين: $usersWithUsernames\n";
    
    $usersWithEmails = User::whereNotNull('email')->count();
    echo "📧 المستخدمين ببريد إلكتروني: $usersWithEmails\n";
    
    // Show sample data
    echo "\n📋 عينة من البيانات:\n";
    $sampleUsers = User::limit(5)->get();
    foreach ($sampleUsers as $user) {
        echo "   • " . $user->username . " - " . $user->name . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
}

echo "\n4. اختبار نظام المصادقة:\n";
echo "----------------------------------------\n";

// Test Laravel authentication
try {
    echo "🔧 فحص إعداد Laravel...\n";
    
    // Check if the user model has the right methods
    $userModel = new User();
    
    if (method_exists($userModel, 'getAuthIdentifierName')) {
        $authField = $userModel->getAuthIdentifierName();
        echo "   ✅ حقل المصادقة: $authField\n";
    } else {
        echo "   ❌ طريقة getAuthIdentifierName غير موجودة\n";
    }
    
    if (method_exists($userModel, 'username')) {
        $usernameField = $userModel->username();
        echo "   ✅ حقل اسم المستخدم: $usernameField\n";
    } else {
        echo "   ❌ طريقة username() غير موجودة\n";
    }
    
} catch (Exception $e) {
    echo "❌ خطأ في فحص Laravel: " . $e->getMessage() . "\n";
}

echo "\n5. تعليمات تسجيل الدخول:\n";
echo "----------------------------------------\n";
echo "🚀 لتجربة تسجيل الدخول:\n";
echo "   1. اذهب إلى: http://localhost/login\n";
echo "   2. استخدم أحد الأسماء التالية:\n";
echo "      • مدير_شامل\n";
echo "      • موظف_مبيعات\n";
echo "      • محاسب\n";
echo "      • مسؤول_مستودع\n";
echo "      • موظف_مستودع\n";
echo "   3. كلمة المرور: password123\n";
echo "\n";

echo "✅ انتهى الفحص!\n";