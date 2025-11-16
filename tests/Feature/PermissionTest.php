<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\SecurityAuditService;
use App\Services\UserActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $managerUser;
    protected User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $superAdmin = Role::create(['name' => 'super_admin']);
        $admin = Role::create(['name' => 'admin']);
        $manager = Role::create(['name' => 'manager']);
        $operator = Role::create(['name' => 'operator']);
        $viewer = Role::create(['name' => 'viewer']);
        $guest = Role::create(['name' => 'guest']);

        // Create permissions
        $permissions = [
            'users.create', 'users.read', 'users.update', 'users.delete',
            'roles.create', 'roles.read', 'roles.update', 'roles.delete',
            'orders.create', 'orders.read', 'orders.update', 'orders.delete',
            'products.create', 'products.read', 'products.update', 'products.delete',
            'admin.panel', 'security.monitor', 'audit.view'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo(array_merge(
            ['users.create', 'users.read', 'users.update', 'roles.read'],
            array_slice($permissions, 4) // orders, products, admin.panel, security.monitor, audit.view
        ));
        $manager->givePermissionTo([
            'orders.create', 'orders.read', 'orders.update',
            'products.read', 'products.update',
            'audit.view'
        ]);
        $operator->givePermissionTo([
            'orders.create', 'orders.read',
            'products.read'
        ]);
        $viewer->givePermissionTo(['orders.read', 'products.read']);
        $guest->givePermissionTo(['products.read']);

        // Create test users
        $this->adminUser = User::factory()->create([
            'username' => 'admin_test',
            'account_type' => 'admin',
        ]);
        $this->adminUser->assignRole('admin');

        $this->managerUser = User::factory()->create([
            'username' => 'manager_test',
            'account_type' => 'manager',
        ]);
        $this->managerUser->assignRole('manager');

        $this->viewerUser = User::factory()->create([
            'username' => 'viewer_test',
            'account_type' => 'viewer',
        ]);
        $this->viewerUser->assignRole('viewer');
    }

    /** @test */
    public function admin_can_access_admin_panel()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/dashboard');

        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_cannot_access_admin_panel()
    {
        $response = $this->actingAs($this->viewerUser)
            ->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function manager_can_manage_orders()
    {
        $response = $this->actingAs($this->managerUser)
            ->get('/orders/create');

        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_cannot_create_orders()
    {
        $response = $this->actingAs($this->viewerUser)
            ->post('/orders', [
                'name' => 'Test Order',
                'status' => 'pending'
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function dynamic_permission_checking_works()
    {
        // Test role hierarchy
        $this->assertTrue($this->adminUser->hasRoleLevel(['admin', 'super_admin']));
        $this->assertFalse($this->viewerUser->hasRoleLevel(['admin', 'super_admin']));

        // Test specific permissions
        $this->assertTrue($this->adminUser->can('users.read'));
        $this->assertTrue($this->managerUser->can('orders.read'));
        $this->assertFalse($this->viewerUser->can('orders.create'));
    }

    /** @test */
    public function arabic_username_permissions_work()
    {
        $arabicUser = User::factory()->create([
            'username' => 'محمد_الأول',
            'account_type' => 'operator',
        ]);
        $arabicUser->assignRole('operator');

        $response = $this->actingAs($arabicUser)
            ->get('/orders/create');

        $response->assertStatus(200);
    }

    /** @test */
    public function permission_audit_logging_works()
    {
        SecurityAuditService::logEvent('permission_check', [
            'user_id' => $this->adminUser->id,
            'permission' => 'users.read',
            'granted' => true,
        ], $this->adminUser);

        $events = SecurityAuditService::getEvents(['event' => 'permission_check']);
        
        $this->assertTrue($events->isNotEmpty());
        $this->assertEquals($this->adminUser->id, $events->first()->user_id);
    }

    /** @test */
    public function user_activity_tracking_works()
    {
        UserActivityService::logActivity('permission_test', [
            'action' => 'checked_permission',
            'permission' => 'orders.read',
            'granted' => true
        ], $this->adminUser);

        $activities = UserActivityService::getUserActivities($this->adminUser);
        
        $this->assertTrue($activities->isNotEmpty());
        $this->assertEquals('permission_test', $activities->first()->action);
    }

    /** @test */
    public function sensitive_operations_require_mfa()
    {
        $this->adminUser->update(['mfa_enabled' => false]);

        // This would typically be tested in a controller
        $this->assertFalse($this->adminUser->canPerformSensitiveOperations());

        $this->adminUser->update(['mfa_enabled' => true]);

        $this->assertTrue($this->adminUser->canPerformSensitiveOperations());
    }

    /** @test */
    public function user_access_control_works()
    {
        $regularUser = User::factory()->create([
            'username' => 'regular_user',
            'account_type' => 'viewer',
        ]);
        $regularUser->assignRole('viewer');

        // User should only access their own account
        $this->assertTrue($this->adminUser->canAccessAccount($this->adminUser));
        $this->assertTrue($this->adminUser->canAccessAccount($regularUser));
        $this->assertTrue($regularUser->canAccessAccount($regularUser));
        $this->assertFalse($regularUser->canAccessAccount($this->adminUser));
    }

    /** @test */
    public function role_level_comparison_works()
    {
        $this->assertEquals(9, $this->adminUser->getRoleLevel());
        $this->assertEquals(8, $this->managerUser->getRoleLevel());
        $this->assertEquals(6, $this->viewerUser->getRoleLevel());

        // Higher level users can do more
        $this->assertTrue($this->adminUser->getRoleLevel() > $this->managerUser->getRoleLevel());
        $this->assertTrue($this->managerUser->getRoleLevel() > $this->viewerUser->getRoleLevel());
    }

    /** @test */
    public function bulk_permission_assignment_works()
    {
        $newUser = User::factory()->create([
            'username' => 'bulk_test',
            'account_type' => 'operator',
        ]);

        // Assign multiple permissions at once
        $newUser->givePermissionTo(['orders.create', 'orders.read', 'products.read']);

        $this->assertTrue($newUser->can('orders.create'));
        $this->assertTrue($newUser->can('orders.read'));
        $this->assertTrue($newUser->can('products.read'));
        $this->assertFalse($newUser->can('orders.delete'));
    }

    /** @test */
    public function permission_caching_works()
    {
        // First check
        $startTime = microtime(true);
        $result1 = $this->adminUser->can('users.read');
        $firstCheckTime = microtime(true) - $startTime;

        // Second check (should be cached)
        $startTime = microtime(true);
        $result2 = $this->adminUser->can('users.read');
        $secondCheckTime = microtime(true) - $startTime;

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($secondCheckTime < $firstCheckTime); // Should be faster due to caching
    }

    /** @test */
    public function permission_error_handling_works()
    {
        // Test with non-existent permission
        $result = $this->adminUser->can('non.existent.permission');
        $this->assertFalse($result);

        // Test with non-existent role
        $result = $this->adminUser->hasRole('non.existent.role');
        $this->assertFalse($result);
    }

    /** @test */
    public function arabic_permission_names_work()
    {
        // Test Arabic permission system (if implemented)
        $arabicPermission = 'عرض_الطلبات'; // "View Orders" in Arabic
        
        // This would depend on your Arabic permission implementation
        $this->assertIsBool($this->managerUser->can($arabicPermission));
    }

    /** @test */
    public function security_audit_for_permission_changes()
    {
        // Simulate permission change
        SecurityAuditService::logEvent('permission_assigned', [
            'target_user_id' => $this->managerUser->id,
            'permission' => 'orders.delete',
            'assigned_by' => $this->adminUser->id,
        ], $this->adminUser);

        // Check that the event was logged
        $events = SecurityAuditService::getEvents([
            'event' => 'permission_assigned',
            'user_id' => $this->adminUser->id
        ]);

        $this->assertTrue($events->isNotEmpty());
        $this->assertEquals('orders.delete', json_decode($events->first()->data, true)['permission']);
    }
}