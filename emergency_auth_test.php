<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== EMERGENCY AUTHENTICATION TEST ===\n\n";

try {
    // Test 1: Check user exists
    echo "1. Checking user exists...\n";
    $user = \App\Models\User::where('username', 'مدير_شامل')->first();
    
    if ($user) {
        echo "   ✓ User found: {$user->username}\n";
        echo "   ✓ User ID: {$user->id}\n";
        echo "   ✓ User email: " . ($user->email ?? 'NULL') . "\n";
        
        // Test 2: Password verification
        echo "\n2. Testing password verification...\n";
        $passwordCheck = password_verify('password123', $user->password);
        echo "   Password 'password123' verification: " . ($passwordCheck ? "✓ PASS" : "✗ FAIL") . "\n";
        
        // Test 3: Auth attempt simulation
        echo "\n3. Testing auth attempt...\n";
        try {
            $authResult = \Auth::attempt(['username' => 'مدير_شامل', 'password' => 'password123']);
            echo "   Auth attempt result: " . ($authResult ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        } catch (Exception $e) {
            echo "   ✗ Auth attempt failed: " . $e->getMessage() . "\n";
        }
        
        // Test 4: User model validation
        echo "\n4. Testing user model validation...\n";
        try {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['username' => 'مدير_شامل', 'password' => 'password123'],
                ['username' => 'required', 'password' => 'required']
            );
            echo "   Basic validation: " . ($validator->passes() ? "✓ PASS" : "✗ FAIL") . "\n";
        } catch (Exception $e) {
            echo "   ✗ Validation error: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "   ✗ User not found!\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING DIFFERENT USERS ===\n\n";

try {
    $testUsers = ['admin', 'test_example_com', 'موظف_مبيعات'];
    
    foreach ($testUsers as $username) {
        echo "Testing user: $username\n";
        $user = \App\Models\User::where('username', $username)->first();
        
        if ($user) {
            $passwordCheck = password_verify('password123', $user->password);
            echo "  ✓ Found, Password check: " . ($passwordCheck ? "PASS" : "FAIL") . "\n";
        } else {
            echo "  ✗ Not found\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error testing users: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "If authentication tests above show PASS, the system should be working.\n";
echo "If they show FAIL, there may be a configuration issue.\n";

?>