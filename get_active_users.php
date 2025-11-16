<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Active Users Query ===\n\n";

$activeUsers = App\Models\User::whereNotNull('email_verified_at')->get();

echo "Total Active Users: " . $activeUsers->count() . "\n\n";

foreach ($activeUsers as $user) {
    echo "Username: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Verification Status: Verified\n";
    echo "---\n\n";
}