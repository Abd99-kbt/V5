<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Debug Test Script for Audit Trail System
 *
 * This script performs step-by-step debugging of the audit trail components
 * to identify where the issue lies.
 */

echo "=== Audit Trail Debug Test ===\n\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "✓ Laravel bootstrapped\n";

try {
    // Test 1: Check database connection
    echo "\n1. Testing Database Connection...\n";
    $dbConnection = DB::connection();
    echo "✓ Database connected: " . $dbConnection->getName() . "\n";

    // Test 2: Check if audit_logs table exists
    echo "\n2. Checking audit_logs table...\n";
    $tableExists = DB::select("SHOW TABLES LIKE 'audit_logs'");
    if (count($tableExists) > 0) {
        echo "✓ audit_logs table exists\n";

        // Check table structure
        $columns = DB::select("DESCRIBE audit_logs");
        echo "✓ Table structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column->Field}: {$column->Type}\n";
        }
    } else {
        echo "✗ audit_logs table does not exist\n";
        exit(1);
    }

    // Test 3: Test direct AuditLog model creation
    echo "\n3. Testing direct AuditLog model creation...\n";
    $testLog = AuditLog::create([
        'auditable_type' => 'App\Models\User',
        'auditable_id' => 1,
        'user_id' => 1,
        'event_type' => 'debug_test',
        'old_values' => ['test' => 'old'],
        'new_values' => ['test' => 'new'],
        'metadata' => ['debug' => true],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Debug Script',
    ]);

    if ($testLog) {
        echo "✓ Direct AuditLog creation successful - ID: {$testLog->id}\n";

        // Verify it was saved
        $saved = AuditLog::find($testLog->id);
        if ($saved) {
            echo "✓ Log verified in database\n";
            echo "  Log data: " . json_encode($saved->toArray(), JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✗ Log not found in database after creation\n";
        }

        // Clean up
        $testLog->delete();
        echo "✓ Test log cleaned up\n";
    } else {
        echo "✗ Direct AuditLog creation failed\n";
        exit(1);
    }

    // Test 4: Test Auditable trait
    echo "\n4. Testing Auditable trait...\n";

    // Check if Order model has Auditable trait
    $orderReflection = new ReflectionClass(Order::class);
    $traits = $orderReflection->getTraitNames();
    $hasAuditable = in_array('App\Traits\Auditable', $traits);

    echo "✓ Order model has Auditable trait: " . ($hasAuditable ? 'Yes' : 'No') . "\n";

    if ($hasAuditable) {
        // Check auditing status
        $auditingEnabled = Order::auditingEnabled();
        echo "✓ Auditing enabled: " . ($auditingEnabled ? 'Yes' : 'No') . "\n";

        if ($auditingEnabled) {
            $auditEvents = (new Order())->getAuditEvents();
            echo "✓ Audit events: " . implode(', ', $auditEvents) . "\n";

            // Test creating an order
            echo "  Creating test order...\n";
            $order = Order::create([
                'order_number' => 'DEBUG-TEST-' . time(),
                'type' => 'out',
                'status' => 'pending',
                'warehouse_id' => 1,
                'customer_name' => 'Debug Test Customer',
                'required_weight' => 100.0,
                'required_date' => now()->addDays(7),
                'order_date' => now(),
                'created_by' => 1,
            ]);

            echo "  ✓ Order created with ID: {$order->id}\n";

            // Check for audit logs
            $auditLogs = AuditLog::where('auditable_type', Order::class)
                                ->where('auditable_id', $order->id)
                                ->get();

            echo "  ✓ Audit logs found: {$auditLogs->count()}\n";

            if ($auditLogs->count() > 0) {
                foreach ($auditLogs as $log) {
                    echo "    - Event: {$log->event_type}, Description: {$log->event_description}\n";
                }
            }

            // Test updating the order
            echo "  Updating order status...\n";
            $order->update(['status' => 'confirmed']);

            $updateLogs = AuditLog::where('auditable_type', Order::class)
                                 ->where('auditable_id', $order->id)
                                 ->where('event_type', 'updated')
                                 ->get();

            echo "  ✓ Update logs found: {$updateLogs->count()}\n";

            // Clean up
            $order->delete();
            echo "  ✓ Test order cleaned up\n";
        }
    }

    // Test 5: Test AuditService
    echo "\n5. Testing AuditService...\n";

    // Check if AuditService class exists
    if (class_exists('App\Services\AuditService')) {
        echo "✓ AuditService class exists\n";

        // Test static method call
        try {
            $serviceLog = \App\Services\AuditService::logSystemEvent('debug_service_test', 'Debug service test');
            if ($serviceLog) {
                echo "✓ AuditService logSystemEvent successful - ID: {$serviceLog->id}\n";

                // Verify in database
                $verified = AuditLog::find($serviceLog->id);
                echo "✓ Service log verified in database: " . ($verified ? 'Yes' : 'No') . "\n";

                // Clean up
                $serviceLog->delete();
                echo "✓ Service test log cleaned up\n";
            } else {
                echo "✗ AuditService logSystemEvent returned null\n";

                // Try to understand why
                echo "  Debugging AuditService...\n";

                // Check if the method exists
                $reflection = new ReflectionClass('App\Services\AuditService');
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);
                $methodNames = array_map(fn($m) => $m->name, $methods);
                echo "  Available static methods: " . implode(', ', $methodNames) . "\n";

                if (in_array('logSystemEvent', $methodNames)) {
                    echo "  logSystemEvent method exists\n";

                    // Try calling with error handling
                    try {
                        $result = call_user_func(['App\Services\AuditService', 'logSystemEvent'], 'debug_test', 'Debug test');
                        echo "  Direct call result: " . ($result ? 'Success' : 'Null') . "\n";

                        if (!$result) {
                            // Try to debug why it returns null
                            echo "  Debugging why logSystemEvent returns null...\n";

                            // Check if we can create a system event directly
                            $directSystemResult = \App\Models\AuditLog::create([
                                'event_type' => 'debug_test',
                                'event_description' => 'Debug test',
                                'auditable_type' => null,
                                'auditable_id' => null,
                                'user_id' => null,
                                'old_values' => [],
                                'new_values' => [],
                                'metadata' => [],
                                'ip_address' => null,
                                'user_agent' => null,
                            ]);
                            echo "  Direct system event creation: " . ($directSystemResult ? 'Success' : 'Null') . "\n";

                            if (!$eventResult) {
                                // Check if AuditLog::create works directly
                                $directResult = \App\Models\AuditLog::create([
                                    'event_type' => 'debug_test',
                                    'event_description' => 'Debug test',
                                    'auditable_type' => null,
                                    'auditable_id' => null,
                                    'user_id' => null,
                                    'old_values' => [],
                                    'new_values' => [],
                                    'metadata' => [],
                                    'ip_address' => null,
                                    'user_agent' => null,
                                ]);
                                echo "  Direct AuditLog::create result: " . ($directResult ? 'Success' : 'Null') . "\n";
                            }
                        }
                    } catch (Exception $e) {
                        echo "  Direct call exception: " . $e->getMessage() . "\n";
                        echo "  Stack trace: " . substr($e->getTraceAsString(), 0, 500) . "...\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "✗ AuditService exception: " . $e->getMessage() . "\n";
            echo "  Stack trace: " . $e->getTraceAsString() . "\n";
        }
    } else {
        echo "✗ AuditService class does not exist\n";
    }

    // Test 6: Summary
    echo "\n6. Debug Summary\n";
    echo str_repeat("-", 30) . "\n";

    $totalLogs = AuditLog::count();
    echo "Total audit logs in database: {$totalLogs}\n";

    $recentLogs = AuditLog::where('created_at', '>=', now()->subMinutes(10))->count();
    echo "Logs created in last 10 minutes: {$recentLogs}\n";

    if ($totalLogs > 0) {
        $eventTypes = AuditLog::select('event_type', DB::raw('count(*) as count'))
                             ->groupBy('event_type')
                             ->get();

        echo "Event type distribution:\n";
        foreach ($eventTypes as $type) {
            echo "  {$type->event_type}: {$type->count}\n";
        }
    }

    echo "\n=== Debug Test Completed ===\n";

} catch (Exception $e) {
    echo "\n✗ Debug test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}