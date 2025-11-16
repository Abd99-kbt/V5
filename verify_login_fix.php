<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Hash;
use App\Models\User;

echo "=== VERIFYING USERS AND TESTING LOGIN ===\n\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "1. Checking users in database...\n";
    $users = User::all();
    
    if ($users->isEmpty()) {
        echo "❌ No users found in database\n";
    } else {
        echo "✓ Found {$users->count()} users:\n";
        foreach ($users as $user) {
            $roles = $user->getRoleNames()->implode(', ');
            echo "  - ID: {$user->id}, Name: {$user->name}, Username: {$user->username}\n";
            echo "    Active: " . ($user->is_active ? 'Yes' : 'No') . ", Roles: {$roles}\n";
        }
    }
    
    echo "\n2. Testing authentication with test credentials...\n";
    
    // Test authentication for the main user
    $testUser = User::where('username', 'مدير_شامل')->first();
    if ($testUser) {
        echo "✓ Found user: {$testUser->name}\n";
        
        // Test password verification
        if (Hash::check('admin123', $testUser->password)) {
            echo "✓ Password 'admin123' is correct for {$testUser->username}\n";
        } else {
            echo "❌ Password 'admin123' is incorrect for {$testUser->username}\n";
        }
    } else {
        echo "❌ User 'مدير_شامل' not found\n";
    }
    
    // Test another user
    $testUser2 = User::where('username', 'test_user')->first();
    if ($testUser2) {
        echo "✓ Found user: {$testUser2->name}\n";
        
        if (Hash::check('password123', $testUser2->password)) {
            echo "✓ Password 'password123' is correct for {$testUser2->username}\n";
        } else {
            echo "❌ Password 'password123' is incorrect for {$testUser2->username}\n";
        }
    } else {
        echo "❌ User 'test_user' not found\n";
    }
    
    echo "\n=== LOGIN INSTRUCTIONS ===\n";
    echo "You can now log in to your system using:\n\n";
    echo "🔐 FILAMENT ADMIN PANEL (Recommended)\n";
    echo "URL: http://localhost:8000/admin/login\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Username: مدير_شامل\n";
    echo "Password: admin123\n";
    echo "Role: مدير_شامل (Full Access)\n\n";
    echo "OR\n\n";
    echo "Username: test_user\n";
    echo "Password: password123\n";
    echo "Role: user (Basic Access)\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n📋 TROUBLESHOOTING:\n";
    echo "• If you still get 'credentials don't match' error:\n";
    echo "  1. Clear your browser cache and cookies\n";
    echo "  2. Make sure you're using the EXACT username and password\n";
    echo "  3. Try the test_user account first (simpler login)\n";
    echo "  4. Check that the server is running: php artisan serve\n";
    echo "• If the page doesn't load:\n";
    echo "  1. Start the server: php artisan serve\n";
    echo "  2. Access: http://localhost:8000/admin/login\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}