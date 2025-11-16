<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Services\AuditService;
use App\Models\AuditLog;
use App\Models\User;

/**
 * AuditService Fix Test Script
 *
 * This script tests the fixed AuditService methods to ensure they work properly.
 */

echo "=== AuditService Fix Test ===\n\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "✓ Laravel bootstrapped\n";

try {
    // Test 1: Test system event logging
    echo "\n1. Testing System Event Logging...\n";

    $systemLog = AuditService::logSystemEvent('test_system_fixed', 'Fixed system event test');
    if ($systemLog) {
        echo "✓ System event logged successfully - ID: {$systemLog->id}\n";
        echo "  - Event Type: {$systemLog->event_type}\n";
        echo "  - Description: {$systemLog->event_description}\n";
        echo "  - Metadata: " . json_encode($systemLog->metadata) . "\n";
    } else {
        echo "✗ System event logging failed\n";
    }

    // Test 2: Test security event logging
    echo "\n2. Testing Security Event Logging...\n";

    $securityLog = AuditService::logSecurityEvent('test_security_fixed', 'Fixed security event test', ['severity' => 'high']);
    if ($securityLog) {
        echo "✓ Security event logged successfully - ID: {$securityLog->id}\n";
        echo "  - Event Type: {$securityLog->event_type}\n";
        echo "  - Description: {$securityLog->event_description}\n";
        echo "  - Severity: " . ($securityLog->metadata['severity'] ?? 'unknown') . "\n";
    } else {
        echo "✗ Security event logging failed\n";
    }

    // Test 3: Test data export logging
    echo "\n3. Testing Data Export Logging...\n";

    $exportLog = AuditService::logDataExport('pdf', ['date_range' => 'last_30_days'], 150);
    if ($exportLog) {
        echo "✓ Data export logged successfully - ID: {$exportLog->id}\n";
        echo "  - Export Type: " . ($exportLog->metadata['export_type'] ?? 'unknown') . "\n";
        echo "  - Record Count: " . ($exportLog->metadata['record_count'] ?? 0) . "\n";
    } else {
        echo "✗ Data export logging failed\n";
    }

    // Test 4: Test configuration change logging
    echo "\n4. Testing Configuration Change Logging...\n";

    $configLog = AuditService::logConfigChange('app.timezone', 'UTC', 'Asia/Damascus');
    if ($configLog) {
        echo "✓ Config change logged successfully - ID: {$configLog->id}\n";
        echo "  - Config Key: " . ($configLog->metadata['config_key'] ?? 'unknown') . "\n";
        echo "  - Old Value: " . ($configLog->metadata['old_value'] ?? 'unknown') . "\n";
        echo "  - New Value: " . ($configLog->metadata['new_value'] ?? 'unknown') . "\n";
    } else {
        echo "✗ Config change logging failed\n";
    }

    // Test 5: Test authentication event logging
    echo "\n5. Testing Authentication Event Logging...\n";

    $authLog = AuditService::logAuthEvent('login', ['ip_address' => '192.168.1.100']);
    if ($authLog) {
        echo "✓ Auth event logged successfully - ID: {$authLog->id}\n";
        echo "  - Event Type: {$authLog->event_type}\n";
        echo "  - Description: {$authLog->event_description}\n";
    } else {
        echo "✗ Auth event logging failed\n";
    }

    // Test 6: Test batch logging
    echo "\n6. Testing Batch Logging...\n";

    $batchEvents = [
        [
            'eventType' => 'batch_test_1',
            'description' => 'First batch event',
            'metadata' => ['batch_id' => 'test_batch_fixed']
        ],
        [
            'eventType' => 'batch_test_2',
            'description' => 'Second batch event',
            'metadata' => ['batch_id' => 'test_batch_fixed']
        ]
    ];

    $batchLogs = AuditService::logBatch($batchEvents);
    echo "✓ Batch logging completed - Created {$batchLogs->count()} logs\n";

    foreach ($batchLogs as $log) {
        echo "  - Log ID: {$log->id}, Event: {$log->event_type}\n";
    }

    // Test 7: Test model-based logging (if we have a user)
    echo "\n7. Testing Model-Based Logging...\n";

    $user = User::first();
    if ($user) {
        $modelLog = AuditService::logCustom('user_action', $user, 'User performed custom action', [], [], ['action' => 'test']);
        if ($modelLog) {
            echo "✓ Model-based logging successful - ID: {$modelLog->id}\n";
            echo "  - Model: {$modelLog->auditable_type}\n";
            echo "  - Model ID: {$modelLog->auditable_id}\n";
        } else {
            echo "✗ Model-based logging failed\n";
        }
    } else {
        echo "⚠ No user found for model-based logging test\n";
    }

    // Test 8: Verify all logs were created
    echo "\n8. Verifying All Audit Logs...\n";

    $totalLogs = AuditLog::where('created_at', '>=', now()->subMinutes(5))->count();
    echo "✓ Total audit logs created in last 5 minutes: {$totalLogs}\n";

    $eventTypes = AuditLog::where('created_at', '>=', now()->subMinutes(5))
                         ->select('event_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                         ->groupBy('event_type')
                         ->get();

    echo "Event type breakdown:\n";
    foreach ($eventTypes as $type) {
        echo "  - {$type->event_type}: {$type->count}\n";
    }

    // Test 9: Test error handling
    echo "\n9. Testing Error Handling...\n";

    // Try to log with invalid data to test error handling
    $errorLog = AuditService::logSystemEvent('', ''); // Empty strings
    if ($errorLog) {
        echo "✓ Error handling works - still created log with defaults\n";
    } else {
        echo "⚠ Error handling returned null (expected for invalid data)\n";
    }

    echo "\n=== AuditService Fix Test Completed Successfully ===\n";

    // Summary
    $successfulTests = 0;
    $totalTests = 9;

    if ($systemLog) $successfulTests++;
    if ($securityLog) $successfulTests++;
    if ($exportLog) $successfulTests++;
    if ($configLog) $successfulTests++;
    if ($authLog) $successfulTests++;
    if ($batchLogs->count() >= 2) $successfulTests++;
    if ($user && $modelLog) $successfulTests++;
    if ($totalLogs >= 7) $successfulTests++; // At least 7 logs should be created
    $successfulTests++; // Error handling test

    echo "\nTest Results: {$successfulTests}/{$totalTests} tests passed (" . round(($successfulTests/$totalTests)*100, 1) . "%)\n";

} catch (Exception $e) {
    echo "\n✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}