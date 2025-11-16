<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define granular permissions for all system areas

        // User Management
        $viewUsers = Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'web']);
        $createUsers = Permission::firstOrCreate(['name' => 'create users', 'guard_name' => 'web']);
        $editUsers = Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
        $deleteUsers = Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => 'web']);
        $manageUsers = Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);

        // Material/Product Management
        $viewMaterials = Permission::firstOrCreate(['name' => 'view materials', 'guard_name' => 'web']);
        $createMaterials = Permission::firstOrCreate(['name' => 'create materials', 'guard_name' => 'web']);
        $editMaterials = Permission::firstOrCreate(['name' => 'edit materials', 'guard_name' => 'web']);
        $deleteMaterials = Permission::firstOrCreate(['name' => 'delete materials', 'guard_name' => 'web']);
        $manageMaterials = Permission::firstOrCreate(['name' => 'manage materials', 'guard_name' => 'web']);

        // Order Management
        $viewOrders = Permission::firstOrCreate(['name' => 'view orders', 'guard_name' => 'web']);
        $createOrders = Permission::firstOrCreate(['name' => 'create orders', 'guard_name' => 'web']);
        $editOrders = Permission::firstOrCreate(['name' => 'edit orders', 'guard_name' => 'web']);
        $deleteOrders = Permission::firstOrCreate(['name' => 'delete orders', 'guard_name' => 'web']);
        $manageOrders = Permission::firstOrCreate(['name' => 'manage orders', 'guard_name' => 'web']);
        $processOrders = Permission::firstOrCreate(['name' => 'process orders', 'guard_name' => 'web']);

        // Invoice Management
        $viewInvoices = Permission::firstOrCreate(['name' => 'view invoices', 'guard_name' => 'web']);
        $createInvoices = Permission::firstOrCreate(['name' => 'create invoices', 'guard_name' => 'web']);
        $editInvoices = Permission::firstOrCreate(['name' => 'edit invoices', 'guard_name' => 'web']);
        $deleteInvoices = Permission::firstOrCreate(['name' => 'delete invoices', 'guard_name' => 'web']);
        $manageInvoices = Permission::firstOrCreate(['name' => 'manage invoices', 'guard_name' => 'web']);

        // Warehouse Management
        $viewWarehouses = Permission::firstOrCreate(['name' => 'view warehouses', 'guard_name' => 'web']);
        $createWarehouses = Permission::firstOrCreate(['name' => 'create warehouses', 'guard_name' => 'web']);
        $editWarehouses = Permission::firstOrCreate(['name' => 'edit warehouses', 'guard_name' => 'web']);
        $deleteWarehouses = Permission::firstOrCreate(['name' => 'delete warehouses', 'guard_name' => 'web']);
        $manageWarehouses = Permission::firstOrCreate(['name' => 'manage warehouses', 'guard_name' => 'web']);
        $manageStock = Permission::firstOrCreate(['name' => 'manage stock', 'guard_name' => 'web']);

        // Customer Management
        $viewCustomers = Permission::firstOrCreate(['name' => 'view customers', 'guard_name' => 'web']);
        $createCustomers = Permission::firstOrCreate(['name' => 'create customers', 'guard_name' => 'web']);
        $editCustomers = Permission::firstOrCreate(['name' => 'edit customers', 'guard_name' => 'web']);
        $deleteCustomers = Permission::firstOrCreate(['name' => 'delete customers', 'guard_name' => 'web']);
        $manageCustomers = Permission::firstOrCreate(['name' => 'manage customers', 'guard_name' => 'web']);

        // Supplier Management
        $viewSuppliers = Permission::firstOrCreate(['name' => 'view suppliers', 'guard_name' => 'web']);
        $createSuppliers = Permission::firstOrCreate(['name' => 'create suppliers', 'guard_name' => 'web']);
        $editSuppliers = Permission::firstOrCreate(['name' => 'edit suppliers', 'guard_name' => 'web']);
        $deleteSuppliers = Permission::firstOrCreate(['name' => 'delete suppliers', 'guard_name' => 'web']);
        $manageSuppliers = Permission::firstOrCreate(['name' => 'manage suppliers', 'guard_name' => 'web']);

        // Category Management
        $viewCategories = Permission::firstOrCreate(['name' => 'view categories', 'guard_name' => 'web']);
        $createCategories = Permission::firstOrCreate(['name' => 'create categories', 'guard_name' => 'web']);
        $editCategories = Permission::firstOrCreate(['name' => 'edit categories', 'guard_name' => 'web']);
        $deleteCategories = Permission::firstOrCreate(['name' => 'delete categories', 'guard_name' => 'web']);
        $manageCategories = Permission::firstOrCreate(['name' => 'manage categories', 'guard_name' => 'web']);

        // Reporting
        $viewReports = Permission::firstOrCreate(['name' => 'view reports', 'guard_name' => 'web']);
        $generateReports = Permission::firstOrCreate(['name' => 'generate reports', 'guard_name' => 'web']);
        $exportReports = Permission::firstOrCreate(['name' => 'export reports', 'guard_name' => 'web']);

        // Delivery Management
        $viewDeliveries = Permission::firstOrCreate(['name' => 'view deliveries', 'guard_name' => 'web']);
        $createDeliveries = Permission::firstOrCreate(['name' => 'create deliveries', 'guard_name' => 'web']);
        $editDeliveries = Permission::firstOrCreate(['name' => 'edit deliveries', 'guard_name' => 'web']);
        $manageDeliveries = Permission::firstOrCreate(['name' => 'manage deliveries', 'guard_name' => 'web']);

        // Stock Alerts
        $viewStockAlerts = Permission::firstOrCreate(['name' => 'view stock alerts', 'guard_name' => 'web']);
        $manageStockAlerts = Permission::firstOrCreate(['name' => 'manage stock alerts', 'guard_name' => 'web']);

        // Transfers
        $viewTransfers = Permission::firstOrCreate(['name' => 'view transfers', 'guard_name' => 'web']);
        $createTransfers = Permission::firstOrCreate(['name' => 'create transfers', 'guard_name' => 'web']);
        $manageTransfers = Permission::firstOrCreate(['name' => 'manage transfers', 'guard_name' => 'web']);

        // Wastes
        $viewWastes = Permission::firstOrCreate(['name' => 'view wastes', 'guard_name' => 'web']);
        $manageWastes = Permission::firstOrCreate(['name' => 'manage wastes', 'guard_name' => 'web']);

        // Work Stages
        $viewWorkStages = Permission::firstOrCreate(['name' => 'view work stages', 'guard_name' => 'web']);
        $manageWorkStages = Permission::firstOrCreate(['name' => 'manage work stages', 'guard_name' => 'web']);

        // Approvals
        $viewApprovals = Permission::firstOrCreate(['name' => 'view approvals', 'guard_name' => 'web']);
        $manageApprovals = Permission::firstOrCreate(['name' => 'manage approvals', 'guard_name' => 'web']);

        // Create the 8 new roles with Arabic names and assign specific permissions

        // 1. أمين_مستودع (Warehouse Keeper)
        $warehouseKeeperRole = Role::firstOrCreate(['name' => 'أمين_مستودع', 'guard_name' => 'web']);
        $warehouseKeeperRole->givePermissionTo([
            $viewOrders, $viewMaterials, $createMaterials, $editMaterials, $manageMaterials,
            $viewWarehouses, $editWarehouses, $manageWarehouses,
            $manageStock, $viewStockAlerts, $manageStockAlerts,
            $viewTransfers, $createTransfers, $manageTransfers,
            $viewWastes, $manageWastes,
            $viewReports, $generateReports
        ]);

        // 2. مسؤول_قصاصة (Cutting Manager)
        $cuttingManagerRole = Role::firstOrCreate(['name' => 'مسؤول_قصاصة', 'guard_name' => 'web']);
        $cuttingManagerRole->givePermissionTo([
            $viewOrders, $editOrders, $processOrders,
            $viewMaterials, $editMaterials,
            $viewWastes, $manageWastes,
            $viewWorkStages, $manageWorkStages,
            $viewReports, $generateReports
        ]);

        // 3. مسؤول_فرازة (Sorting Manager)
        $sortingManagerRole = Role::firstOrCreate(['name' => 'مسؤول_فرازة', 'guard_name' => 'web']);
        $sortingManagerRole->givePermissionTo([
            $viewOrders, $editOrders, $processOrders,
            $viewMaterials, $editMaterials,
            $viewWastes, $manageWastes,
            $viewWorkStages, $manageWorkStages,
            $viewReports, $generateReports
        ]);

        // 4. محاسب (Accountant)
        $accountantRole = Role::firstOrCreate(['name' => 'محاسب', 'guard_name' => 'web']);
        $accountantRole->givePermissionTo([
            $viewInvoices, $createInvoices, $editInvoices, $manageInvoices,
            $viewOrders, $viewCustomers, $viewSuppliers,
            $viewReports, $generateReports, $exportReports
        ]);

        // 5. متابع_طلبات (Order Tracker)
        $orderTrackerRole = Role::firstOrCreate(['name' => 'متابع_طلبات', 'guard_name' => 'web']);
        $orderTrackerRole->givePermissionTo([
            $viewOrders, $editOrders, $processOrders,
            $viewMaterials, $viewCustomers, $viewSuppliers,
            $viewDeliveries, $viewApprovals,
            $viewReports, $generateReports, $exportReports
        ]);

        // 6. مسؤول_تسليم (Delivery Manager)
        $deliveryManagerRole = Role::firstOrCreate(['name' => 'مسؤول_تسليم', 'guard_name' => 'web']);
        $deliveryManagerRole->givePermissionTo([
            $viewOrders, $editOrders, $processOrders,
            $viewDeliveries, $createDeliveries, $editDeliveries, $manageDeliveries,
            $viewCustomers, $viewMaterials,
            $viewReports, $generateReports
        ]);

        // 7. موظف_مبيعات (Sales Employee)
        $salesEmployeeRole = Role::firstOrCreate(['name' => 'موظف_مبيعات', 'guard_name' => 'web']);
        $salesEmployeeRole->givePermissionTo([
            $viewCustomers, $createCustomers, $editCustomers, $manageCustomers,
            $viewOrders, $createOrders, $editOrders,
            $viewMaterials, $viewCategories,
            $viewReports, $generateReports
        ]);

        // 8. مدير_مبيعات (Sales Manager)
        $salesManagerRole = Role::firstOrCreate(['name' => 'مدير_مبيعات', 'guard_name' => 'web']);
        $salesManagerRole->givePermissionTo([
            $viewCustomers, $createCustomers, $editCustomers, $manageCustomers,
            $viewOrders, $createOrders, $editOrders, $processOrders,
            $viewMaterials, $editMaterials, $manageMaterials, $viewCategories,
            $viewSuppliers, $manageSuppliers,
            $viewReports, $generateReports, $exportReports
        ]);

        // 9. مسؤول_مستودع (Warehouse Manager)
        $warehouseManagerRole = Role::firstOrCreate(['name' => 'مسؤول_مستودع', 'guard_name' => 'web']);
        $warehouseManagerRole->givePermissionTo([
            $viewOrders, $viewMaterials, $createMaterials, $editMaterials, $manageMaterials,
            $viewWarehouses, $createWarehouses, $editWarehouses, $manageWarehouses,
            $manageStock, $viewStockAlerts, $manageStockAlerts,
            $viewTransfers, $createTransfers, $manageTransfers,
            $viewWastes, $manageWastes,
            $viewWorkStages, $manageWorkStages,
            $viewReports, $generateReports, $exportReports
        ]);

        // 10. موظف_مستودع (Warehouse Employee)
        $warehouseEmployeeRole = Role::firstOrCreate(['name' => 'موظف_مستودع', 'guard_name' => 'web']);
        $warehouseEmployeeRole->givePermissionTo([
            $viewOrders, $viewMaterials, $editMaterials,
            $viewWarehouses, $editWarehouses,
            $manageStock, $viewStockAlerts,
            $viewTransfers, $createTransfers,
            $viewWastes, $manageWastes,
            $viewReports, $generateReports
        ]);

        // 8. مدير_شامل (General Manager) - Full system access including creating products and suppliers
        $generalManagerRole = Role::firstOrCreate(['name' => 'مدير_شامل', 'guard_name' => 'web']);
        $generalManagerRole->givePermissionTo([
            $manageUsers, $createUsers, $editUsers, $deleteUsers, $viewUsers,
            $manageMaterials, $createMaterials, $editMaterials, $viewMaterials, $deleteMaterials,
            $manageOrders, $createOrders, $editOrders, $viewOrders, $processOrders,
            $manageInvoices, $createInvoices, $editInvoices, $viewInvoices,
            $manageWarehouses, $createWarehouses, $editWarehouses, $viewWarehouses,
            $manageCustomers, $createCustomers, $editCustomers, $viewCustomers,
            $manageSuppliers, $createSuppliers, $editSuppliers, $viewSuppliers, $deleteSuppliers,
            $manageCategories, $createCategories, $editCategories, $viewCategories,
            $manageDeliveries, $createDeliveries, $editDeliveries, $viewDeliveries,
            $manageStock, $viewStockAlerts, $manageStockAlerts,
            $manageTransfers, $createTransfers, $viewTransfers,
            $manageWastes, $viewWastes,
            $manageWorkStages, $viewWorkStages,
            $manageApprovals, $viewApprovals,
            $viewReports, $generateReports, $exportReports
        ]);

        // Keep existing roles for backward compatibility
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo([$manageUsers, $manageMaterials, $manageOrders, $viewReports]);

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([$manageMaterials, $manageOrders, $viewReports]);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->givePermissionTo([$viewReports]);
    }
}
