<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions and roles
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'AdminUserSeeder']);

        // Create test data
        $this->artisan('db:seed', ['--class' => 'WarehouseSeeder']);
        $this->artisan('db:seed', ['--class' => 'CategorySeeder']);
        $this->artisan('db:seed', ['--class' => 'SupplierSeeder']);
        $this->artisan('db:seed', ['--class' => 'CustomerSeeder']);
        $this->artisan('db:seed', ['--class' => 'ProductSeeder']);
    }

    public function test_roles_are_created_with_correct_permissions()
    {
        // Test that all roles exist
        $roles = [
            'أمين_مستودع', // Warehouse Keeper
            'مسؤول_قصاصة', // Cutting Manager
            'مسؤول_فرازة', // Sorting Manager
            'محاسب', // Accountant
            'متابع_طلبات', // Order Tracker
            'مسؤول_تسليم', // Delivery Manager
            'موظف_مبيعات', // Sales Employee
            'مدير_شامل', // General Manager
            'admin',
            'manager',
            'user'
        ];

        foreach ($roles as $roleName) {
            $this->assertTrue(Role::where('name', $roleName)->exists(), "Role {$roleName} should exist");
        }

        $this->assertEquals(count($roles), Role::count(), 'All expected roles should be created');
    }

    public function test_permissions_are_assigned_correctly_to_roles()
    {
        $warehouseKeeper = Role::where('name', 'أمين_مستودع')->first();
        $this->assertNotNull($warehouseKeeper, 'Warehouse Keeper role should exist');

        // Warehouse Keeper should have warehouse and stock permissions
        $this->assertTrue($warehouseKeeper->hasPermissionTo('view warehouses'));
        $this->assertTrue($warehouseKeeper->hasPermissionTo('manage stock'));
        $this->assertTrue($warehouseKeeper->hasPermissionTo('view stock alerts'));

        // But should not have user management permissions
        $this->assertFalse($warehouseKeeper->hasPermissionTo('manage users'));

        $generalManager = Role::where('name', 'مدير_شامل')->first();
        $this->assertNotNull($generalManager, 'General Manager role should exist');

        // General Manager should have all permissions
        $this->assertTrue($generalManager->hasPermissionTo('manage users'));
        $this->assertTrue($generalManager->hasPermissionTo('manage orders'));
        $this->assertTrue($generalManager->hasPermissionTo('manage invoices'));
        $this->assertTrue($generalManager->hasPermissionTo('view reports'));
    }

    public function test_users_can_be_assigned_roles()
    {
        $user = User::factory()->create();

        $warehouseKeeperRole = Role::where('name', 'أمين_مستودع')->first();
        $user->assignRole($warehouseKeeperRole);

        $this->assertTrue($user->hasRole('أمين_مستودع'));
        $this->assertTrue($user->hasPermissionTo('view warehouses'));
    }

    public function test_role_based_access_control_for_orders()
    {
        // Create test users with different roles
        $warehouseKeeper = User::factory()->create();
        $warehouseKeeper->assignRole('أمين_مستودع');

        $salesEmployee = User::factory()->create();
        $salesEmployee->assignRole('موظف_مبيعات');

        $generalManager = User::factory()->create();
        $generalManager->assignRole('مدير_شامل');

        // Create test order
        $customer = Customer::first();
        $warehouse = Warehouse::first();
        $user = User::first();

        $order = Order::create([
            'order_number' => 'TEST-001',
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'material_type' => 'كرتون',
            'required_weight' => 1000,
            'estimated_price' => 1500,
            'delivery_method' => 'استلام_ذاتي',
            'order_date' => now()->toDateString(),
        ]);

        // Test that different roles have appropriate access
        // Sales employee should be able to view orders they created
        $order->created_by = $salesEmployee->id;
        $order->status = 'مسودة'; // Draft status
        $order->save();

        $this->assertTrue($order->canBeModifiedBy($salesEmployee), 'Sales employee should be able to modify their own orders');

        // Warehouse keeper should not be able to modify orders they didn't create
        $this->assertFalse($order->canBeModifiedBy($warehouseKeeper), 'Warehouse keeper should not be able to modify orders they did not create');

        // General manager should be able to modify any order
        $this->assertTrue($order->canBeModifiedBy($generalManager), 'General manager should be able to modify any order');
    }

    public function test_permission_based_resource_access()
    {
        // Create users with different roles
        $accountant = User::factory()->create();
        $accountant->assignRole('محاسب');

        $warehouseKeeper = User::factory()->create();
        $warehouseKeeper->assignRole('أمين_مستودع');

        // Accountant should have invoice permissions
        $this->assertTrue($accountant->hasPermissionTo('view invoices'));
        $this->assertTrue($accountant->hasPermissionTo('create invoices'));
        $this->assertTrue($accountant->hasPermissionTo('manage invoices'));

        // Warehouse keeper should not have invoice permissions
        $this->assertFalse($warehouseKeeper->hasPermissionTo('view invoices'));
        $this->assertFalse($warehouseKeeper->hasPermissionTo('create invoices'));

        // But warehouse keeper should have warehouse permissions
        $this->assertTrue($warehouseKeeper->hasPermissionTo('view warehouses'));
        $this->assertTrue($warehouseKeeper->hasPermissionTo('manage stock'));
    }

    public function test_admin_user_has_correct_role()
    {
        $adminUser = User::where('email', 'admin@admin.com')->first();
        $this->assertNotNull($adminUser, 'Admin user should exist');

        $this->assertTrue($adminUser->hasRole('admin'), 'Admin user should have admin role');
        $this->assertTrue($adminUser->hasPermissionTo('manage users'), 'Admin should have user management permissions');
    }

    public function test_role_hierarchy_and_inheritance()
    {
        $generalManager = Role::where('name', 'مدير_شامل')->first();
        $salesEmployee = Role::where('name', 'موظف_مبيعات')->first();

        // General manager should have more permissions than sales employee
        $generalManagerPermissions = $generalManager->permissions->pluck('name');
        $salesEmployeePermissions = $salesEmployee->permissions->pluck('name');

        $this->assertTrue($generalManagerPermissions->count() > $salesEmployeePermissions->count(),
            'General manager should have more permissions than sales employee');

        // General manager should have more permissions than sales employee
        $this->assertTrue($generalManagerPermissions->count() > $salesEmployeePermissions->count(),
            'General manager should have more permissions than sales employee');

        // Test that general manager has some key permissions
        $this->assertTrue($generalManagerPermissions->contains('manage users'));
        $this->assertTrue($generalManagerPermissions->contains('manage orders'));
        $this->assertTrue($generalManagerPermissions->contains('view reports'));
    }

    public function test_all_permissions_are_created()
    {
        $expectedPermissions = [
            // User Management
            'view users', 'create users', 'edit users', 'delete users', 'manage users',

            // Material/Product Management
            'view materials', 'create materials', 'edit materials', 'delete materials', 'manage materials',

            // Order Management
            'view orders', 'create orders', 'edit orders', 'delete orders', 'manage orders', 'process orders',

            // Invoice Management
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'manage invoices',

            // Warehouse Management
            'view warehouses', 'create warehouses', 'edit warehouses', 'delete warehouses', 'manage warehouses', 'manage stock',

            // Customer Management
            'view customers', 'create customers', 'edit customers', 'delete customers', 'manage customers',

            // Supplier Management
            'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers', 'manage suppliers',

            // Category Management
            'view categories', 'create categories', 'edit categories', 'delete categories', 'manage categories',

            // Reporting
            'view reports', 'generate reports', 'export reports',

            // Delivery Management
            'view deliveries', 'create deliveries', 'edit deliveries', 'manage deliveries',

            // Stock Alerts
            'view stock alerts', 'manage stock alerts',

            // Transfers
            'view transfers', 'create transfers', 'manage transfers',

            // Wastes
            'view wastes', 'manage wastes',

            // Work Stages
            'view work stages', 'manage work stages',

            // Approvals
            'view approvals', 'manage approvals',
        ];

        foreach ($expectedPermissions as $permissionName) {
            $this->assertTrue(Permission::where('name', $permissionName)->exists(),
                "Permission '{$permissionName}' should exist");
        }

        $this->assertEquals(count($expectedPermissions), Permission::count(),
            'All expected permissions should be created');
    }
}