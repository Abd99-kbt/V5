<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class DatabaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $testUsers;
    private $testCustomers;
    private $testOrders;

    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'database.connections.mysql.strict' => true,
            'database.connections.mysql.modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ]
        ]);
        
        // إنشاء بيانات اختبار
        $this->testUsers = User::factory()->count(10)->create();
        $this->testCustomers = Customer::factory()->count(20)->create();
        $this->testOrders = Order::factory()->count(50)->create();
    }

    /** @test */
    public function test_sql_injection_prevention()
    {
        $maliciousPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "' UNION SELECT * FROM users --",
            "' OR 1=1#"
        ];

        foreach ($maliciousPayloads as $payload) {
            $response = $this->get('/admin/orders?search=' . urlencode($payload));
            $response->assertStatus(200);
            
            $loginResponse = $this->post('/login', [
                'username' => $payload,
                'password' => 'any_password'
            ]);
            $loginResponse->assertStatus(422);
        }
    }

    /** @test */
    public function test_data_access_control()
    {
        $adminUser = $this->testUsers->first();
        $adminUser->assignRole('admin');
        
        $regularUser = $this->testUsers->skip(1)->first();
        $regularUser->assignRole('user');
        
        $adminResponse = $this->actingAs($adminUser)->get('/admin/orders');
        $adminResponse->assertStatus(200);
        
        $userResponse = $this->actingAs($regularUser)->get('/admin/orders');
        $userResponse->assertStatus(403);
    }

    /** @test */
    public function test_data_integrity_checks()
    {
        $user = $this->testUsers->first();
        
        $integrityAttacks = [
            "'; UPDATE users SET role='admin' WHERE id=1; --",
            "'; DELETE FROM users WHERE id>1; --"
        ];
        
        foreach ($integrityAttacks as $attack) {
            $response = $this->post('/orders', [
                'customer_id' => 1,
                'total_amount' => 100,
                'note' => $attack
            ]);
            $response->assertStatus(422);
        }
    }
}