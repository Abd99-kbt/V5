<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Hash;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔐 Testing User Authentication\n";
echo "=============================\n\n";

$users = App\Models\User::with('roles')->get();

$failedLogins = [];
$successfulLogins = [];

foreach ($users as $user) {
    try {
        // Test password verification
        if (Hash::check('password123', $user->password)) {
            $successfulLogins[] = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'No Role',
                'authenticated' => true
            ];
        } else {
            $failedLogins[] = [
                'name' => $user->name,
                'email' => $user->email,
                'authenticated' => false,
                'reason' => 'Password verification failed'
            ];
        }
    } catch (Exception $e) {
        $failedLogins[] = [
            'name' => $user->name,
            'email' => $user->email,
            'authenticated' => false,
            'reason' => 'Authentication error: ' . $e->getMessage()
        ];
    }
}

echo "✅ Successful Logins (" . count($successfulLogins) . "):\n";
foreach ($successfulLogins as $login) {
    echo "  • " . $login['name'] . " (" . $login['email'] . ") - Role: " . $login['role'] . "\n";
}

if (count($failedLogins) > 0) {
    echo "\n❌ Failed Logins (" . count($failedLogins) . "):\n";
    foreach ($failedLogins as $login) {
        echo "  • " . $login['name'] . " (" . $login['email'] . ") - " . $login['reason'] . "\n";
    }
}

echo "\n🎯 Summary:\n";
echo "  Total Users: " . count($users) . "\n";
echo "  Successful Auth: " . count($successfulLogins) . "\n";
echo "  Failed Auth: " . count($failedLogins) . "\n";

if (count($failedLogins) === 0) {
    echo "\n🎉 All users are ready for testing!\n";
    echo "   Use email + 'password123' to login\n";
} else {
    echo "\n⚠️  Some users need attention\n";
}