<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;

try {
    $customer = Customer::create([
        'name_en' => 'شركة التجارة المتقدمة',
        'name_ar' => 'شركة التجارة المتقدمة',
        'province_en' => 'دمشق',
        'province_ar' => 'دمشق',
        'mobile_number' => '0933123456',
        'follow_up_person_en' => 'أحمد السالم',
        'follow_up_person_ar' => 'أحمد السالم',
        'is_active' => true,
    ]);

    // Verify the insertion
    $exists = Customer::where('name_ar', 'شركة التجارة المتقدمة')
                      ->where('province_ar', 'دمشق')
                      ->where('mobile_number', '0933123456')
                      ->where('follow_up_person_ar', 'أحمد السالم')
                      ->exists();

    if ($exists) {
        echo "Client 'شركة التجارة المتقدمة' added successfully and verified in the database.\n";
    } else {
        echo "Client addition failed verification.\n";
    }
} catch (Exception $e) {
    echo "Error adding client: " . $e->getMessage() . "\n";
}