<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\LoginAttemptService;
use App\Services\SecurityAuditService;
use App\Services\PasswordPolicyService;
use App\Services\UserActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthenticationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        User::factory()->count(1000)->create();
    }

    /** @test */
    public function authentication_performance_test()
    {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $user = User::inRandomOrder()->first();
            
            $authService = new AuthenticationService();
            $result = $authService->authenticate(
                new \Illuminate\Http\Request([
                    'username' => $user->username,
                    'password' => 'password'
                ]),
                $user->username,
                'password'
            );
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.1, $avgTime, "Average authentication time should be less than 100ms");
    }

    /** @test */
    public function permission_check_performance_test()
    {
        $startTime = microtime(true);
        $iterations = 1000;
        $user = User::factory()->create();

        // Assign some permissions
        $user->givePermissionTo(['users.read', 'orders.create', 'products.read']);

        for ($i = 0; $i < $iterations; $i++) {
            $user->can('users.read');
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.001, $avgTime, "Average permission check should be less than 1ms");
    }

    /** @test */
    public function password_validation_performance_test()
    {
        $startTime = microtime(true);
        $iterations = 500;

        for ($i = 0; $i < $iterations; $i++) {
            $password = 'TestPassword' . rand(1000, 9999) . '!';
            PasswordPolicyService::validatePassword($password);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.01, $avgTime, "Average password validation should be less than 10ms");
    }

    /** @test */
    public function security_audit_logging_performance_test()
    {
        $startTime = microtime(true);
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            SecurityAuditService::logEvent('test_event_' . $i, [
                'test_data' => 'performance test data',
                'iteration' => $i,
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.05, $avgTime, "Average security logging should be less than 50ms");
    }

    /** @test */
    public function user_activity_tracking_performance_test()
    {
        $user = User::factory()->create();
        $startTime = microtime(true);
        $iterations = 300;

        for ($i = 0; $i < $iterations; $i++) {
            UserActivityService::logActivity('test_activity_' . $i, [
                'activity_data' => 'performance test data',
                'iteration' => $i,
            ], $user);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.02, $avgTime, "Average activity logging should be less than 20ms");
    }

    /** @test */
    public function login_attempt_monitoring_performance_test()
    {
        $startTime = microtime(true);
        $iterations = 150;

        for ($i = 0; $i < $iterations; $i++) {
            LoginAttemptService::recordAttempt(
                'test_user_' . ($i % 10),
                '192.168.1.' . (100 + ($i % 50)),
                $i % 5 === 0 // 20% success rate
            );
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.03, $avgTime, "Average login attempt tracking should be less than 30ms");
    }

    /** @test */
    public function concurrent_authentication_test()
    {
        $user = User::factory()->create([
            'username' => 'concurrent_user',
            'password' => Hash::make('TestPassword123!')
        ]);

        $startTime = microtime(true);
        $iterations = 50;
        $threads = 5;
        $authService = new AuthenticationService();

        // Simulate concurrent authentication attempts
        $promises = [];
        
        for ($thread = 0; $thread < $threads; $thread++) {
            $promises[] = function() use ($authService, $user, $iterations) {
                for ($i = 0; $i < $iterations; $i++) {
                    $authService->authenticate(
                        new \Illuminate\Http\Request([
                            'username' => $user->username,
                            'password' => 'TestPassword123!'
                        ]),
                        $user->username,
                        'TestPassword123!'
                    );
                }
            };
        }

        // Execute all threads concurrently (simulated)
        foreach ($promises as $promise) {
            $promise();
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $avgTimePerThread = $totalTime / ($iterations * $threads);

        $this->assertLessThan(0.05, $avgTimePerThread, "Concurrent authentication should be efficient");
    }

    /** @test */
    public function database_query_performance_test()
    {
        // Create users with various roles and permissions
        $roles = ['admin', 'manager', 'operator', 'viewer'];
        $permissions = ['users.read', 'orders.create', 'products.update', 'reports.generate'];
        
        foreach ($roles as $roleName) {
            foreach ($permissions as $permission) {
                $user = User::factory()->create([
                    'account_type' => $roleName,
                ]);
                $user->assignRole($roleName);
                if (rand(0, 1)) { // Random assignment
                    $user->givePermissionTo($permission);
                }
            }
        }

        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $user = User::with(['roles', 'permissions'])->inRandomOrder()->first();
            $hasPermission = $user->can($permissions[array_rand($permissions)]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(0.05, $avgTime, "Database query with eager loading should be efficient");
    }

    /** @test */
    public function cache_performance_test()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['users.read', 'orders.create']);

        // First access (cache miss)
        $startTime1 = microtime(true);
        $result1 = $user->can('users.read');
        $time1 = microtime(true) - $startTime1;

        // Second access (cache hit)
        $startTime2 = microtime(true);
        $result2 = $user->can('users.read');
        $time2 = microtime(true) - $startTime2;

        $speedImprovement = $time1 / $time2;

        $this->assertTrue($result1 === $result2, "Results should be consistent");
        $this->assertGreaterThan(5, $speedImprovement, "Cache should provide significant speed improvement");
    }

    /** @test */
    public function memory_usage_test()
    {
        $initialMemory = memory_get_usage();
        
        // Create many users
        $users = User::factory()->count(1000)->create();
        
        // Perform various operations
        foreach ($users as $user) {
            $user->can('users.read');
            UserActivityService::logActivity('test_activity', [], $user);
            SecurityAuditService::logEvent('test_security_event', [], $user);
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, "Memory usage should be reasonable");
    }

    /** @test */
    public function bulk_operation_performance_test()
    {
        $users = User::factory()->count(500)->create();
        
        // Bulk permission assignment
        $startTime = microtime(true);
        
        foreach ($users as $user) {
            $user->givePermissionTo(['users.read', 'orders.create']);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = $duration / count($users);
        
        $this->assertLessThan(0.02, $avgTime, "Bulk permission assignment should be efficient");
    }

    /** @test */
    public function stress_test_high_volume_logins()
    {
        $attackerIp = '192.168.1.100';
        $userCount = 100;
        
        $startTime = microtime(true);
        
        // Simulate high volume login attempts
        for ($i = 0; $i < $userCount * 10; $i++) {
            $username = 'user_' . rand(1, $userCount);
            LoginAttemptService::recordAttempt($username, $attackerIp, false);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $operationsPerSecond = ($userCount * 10) / $duration;
        
        $this->assertGreaterThan(100, $operationsPerSecond, "Should handle high volume operations efficiently");
    }
}