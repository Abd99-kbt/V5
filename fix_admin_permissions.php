<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;

echo "=== FIXING ADMIN USER PERMISSIONS ===\n\n";

// Get the admin user
$adminUser = User::where('username', 'admin')->first();

if (!$adminUser) {
    echo "❌ Admin user not found!\n";
    exit(1);
}

echo "Admin User: " . $adminUser->username . "\n";
echo "Current roles: " . $adminUser->getRoleNames()->implode(', ') . "\n\n";

// Make sure the admin user has the correct permissions
$requiredPermissions = [
    'manage users',
    'view users',
    'create users',
    'edit users',
    'delete users'
];

echo "=== Checking/Creating Required Permissions ===\n";
foreach ($requiredPermissions as $permissionName) {
    // Check if permission exists with both guards
    $webPerm = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
    $usernamePerm = Permission::where('name', $permissionName)->where('guard_name', 'username')->first();
    
    echo "Permission: $permissionName\n";
    echo "  - Web guard: " . ($webPerm ? '✅ EXISTS' : '❌ MISSING') . "\n";
    echo "  - Username guard: " . ($usernamePerm ? '✅ EXISTS' : '❌ MISSING') . "\n";
    
    // Sync permissions for both guards
    if ($webPerm) {
        if (!$adminUser->hasPermissionTo($webPerm)) {
            $adminUser->givePermissionTo($webPerm);
            echo "    ✅ Added web guard permission\n";
        }
    }
    
    if ($usernamePerm) {
        if (!$adminUser->hasPermissionTo($usernamePerm)) {
            $adminUser->givePermissionTo($usernamePerm);
            echo "    ✅ Added username guard permission\n";
        }
    }
    echo "\n";
}

// Clear and refresh permissions cache
echo "=== Clearing Permission Cache ===\n";
app()[Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "✅ Permission cache cleared\n\n";

// Verify final permissions
echo "=== Final Verification ===\n";
$finalPermissions = $adminUser->getAllPermissions()->pluck('name');
echo "All permissions: " . $finalPermissions->implode(', ') . "\n";

echo "\n=== Permission Checks ===\n";
foreach ($requiredPermissions as $perm) {
    $hasIt = $adminUser->can($perm);
    echo ($hasIt ? '✅' : '❌') . " $perm: " . ($hasIt ? 'YES' : 'NO') . "\n";
}

echo "\n=== UserResource Access Test ===\n";
$canViewAny = $adminUser->can('view users') || $adminUser->can('manage users');
echo "canViewAny(): " . ($canViewAny ? '✅ YES' : '❌ NO') . "\n";

if ($canViewAny) {
    echo "\n🎉 SUCCESS: Admin user should now have access to user management!\n";
    echo "\nNext steps:\n";
    echo "1. Clear browser cache\n";
    echo "2. Log out and log back in with admin@admin.com / password\n";
    echo "3. Navigate to /admin/users\n";
} else {
    echo "\n❌ STILL NO ACCESS: Additional troubleshooting needed\n";
}

echo "\n=== Testing Alternative Solution ===\n";
echo "If the above doesn't work, try creating a permission bridge:\n";

// Create a test by manually checking what UserResource will check
$userResourceCheck = (Auth::check() && (Auth::user()->can('view users') || Auth::user()->can('manage users')));
echo "Manual UserResource check: " . ($userResourceCheck ? '✅ PASS' : '❌ FAIL') . "\n";