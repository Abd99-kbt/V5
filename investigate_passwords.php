<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== USER PASSWORD INVESTIGATION ===\n\n";

try {
    // Get all users
    $users = \App\Models\User::all();

    if ($users->isEmpty()) {
        echo "No users found in the database.\n";
        exit;
    }

    echo "Found " . $users->count() . " users in the database:\n\n";

    foreach ($users as $user) {
        echo "User ID: {$user->id}\n";
        echo "Username: {$user->username}\n";
        echo "Name: {$user->name}\n";
        echo "Email: " . ($user->email ?: 'Not set') . "\n";
        echo "Hashed Password: {$user->password}\n";

        // Check if password is properly hashed (starts with $2y$ for bcrypt)
        if (password_get_info($user->password)['algo'] === PASSWORD_BCRYPT) {
            echo "✓ Password is properly hashed (bcrypt)\n";
        } else {
            echo "✗ Password is NOT properly hashed (plain text or other format)\n";
        }

        // Check against known passwords from seeder
        $expectedPasswords = ['password123', 'password'];

        $matchedPassword = null;
        foreach ($expectedPasswords as $expected) {
            if (Hash::check($expected, $user->password)) {
                $matchedPassword = $expected;
                break;
            }
        }

        if ($matchedPassword) {
            echo "✓ Password matches expected: '{$matchedPassword}'\n";
        } else {
            echo "✗ Password does NOT match any expected values\n";
        }

        // Check password length (hashed passwords are longer)
        if (strlen($user->password) < 60) {
            echo "⚠ Warning: Password field is suspiciously short (may be plain text)\n";
        }

        echo "Created: {$user->created_at}\n";
        echo "Updated: {$user->updated_at}\n";
        echo str_repeat("-", 50) . "\n\n";
    }

    // Summary
    echo "=== SUMMARY ===\n";
    $properlyHashed = $users->filter(function($user) {
        return password_get_info($user->password)['algo'] === PASSWORD_BCRYPT;
    })->count();

    $matchedExpected = $users->filter(function($user) {
        return Hash::check('password123', $user->password) || Hash::check('password', $user->password);
    })->count();

    echo "Total users: {$users->count()}\n";
    echo "Properly hashed passwords: {$properlyHashed}\n";
    echo "Passwords matching expected values: {$matchedExpected}\n";

    if ($properlyHashed === $users->count()) {
        echo "✓ All passwords are properly hashed\n";
    } else {
        echo "✗ Some passwords are not properly hashed\n";
    }

    if ($matchedExpected === $users->count()) {
        echo "✓ All passwords match expected values\n";
    } else {
        echo "✗ Some passwords do not match expected values\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}