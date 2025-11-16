<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User Management
        Permission::firstOrCreate(['name' => 'view users']);
        Permission::firstOrCreate(['name' => 'create users']);
        Permission::firstOrCreate(['name' => 'edit users']);
        Permission::firstOrCreate(['name' => 'delete users']);
        Permission::firstOrCreate(['name' => 'manage users']);

        // Material/Product Management
        Permission::firstOrCreate(['name' => 'view materials']);
        Permission::firstOrCreate(['name' => 'create materials']);
        Permission::firstOrCreate(['name' => 'edit materials']);
        Permission::firstOrCreate(['name' => 'delete materials']);
        Permission::firstOrCreate(['name' => 'manage materials']);

        // Order Management
        Permission::firstOrCreate(['name' => 'view orders']);
        Permission::firstOrCreate(['name' => 'create orders']);
        Permission::firstOrCreate(['name' => 'edit orders']);
        Permission::firstOrCreate(['name' => 'delete orders']);
        Permission::firstOrCreate(['name' => 'manage orders']);
        Permission::firstOrCreate(['name' => 'process orders']);

        // Invoice Management
        Permission::firstOrCreate(['name' => 'view invoices']);
        Permission::firstOrCreate(['name' => 'create invoices']);
        Permission::firstOrCreate(['name' => 'edit invoices']);
        Permission::firstOrCreate(['name' => 'delete invoices']);
        Permission::firstOrCreate(['name' => 'manage invoices']);

        // Warehouse Management
        Permission::firstOrCreate(['name' => 'view warehouses']);
        Permission::firstOrCreate(['name' => 'create warehouses']);
        Permission::firstOrCreate(['name' => 'edit warehouses']);
        Permission::firstOrCreate(['name' => 'delete warehouses']);
        Permission::firstOrCreate(['name' => 'manage warehouses']);
        Permission::firstOrCreate(['name' => 'manage stock']);

        // Customer Management
        Permission::firstOrCreate(['name' => 'view customers']);
        Permission::firstOrCreate(['name' => 'create customers']);
        Permission::firstOrCreate(['name' => 'edit customers']);
        Permission::firstOrCreate(['name' => 'delete customers']);
        Permission::firstOrCreate(['name' => 'manage customers']);

        // Supplier Management
        Permission::firstOrCreate(['name' => 'view suppliers']);
        Permission::firstOrCreate(['name' => 'create suppliers']);
        Permission::firstOrCreate(['name' => 'edit suppliers']);
        Permission::firstOrCreate(['name' => 'delete suppliers']);
        Permission::firstOrCreate(['name' => 'manage suppliers']);

        // Category Management
        Permission::firstOrCreate(['name' => 'view categories']);
        Permission::firstOrCreate(['name' => 'create categories']);
        Permission::firstOrCreate(['name' => 'edit categories']);
        Permission::firstOrCreate(['name' => 'delete categories']);
        Permission::firstOrCreate(['name' => 'manage categories']);

        // Reporting
        Permission::firstOrCreate(['name' => 'view reports']);
        Permission::firstOrCreate(['name' => 'generate reports']);
        Permission::firstOrCreate(['name' => 'export reports']);

        // Delivery Management
        Permission::firstOrCreate(['name' => 'view deliveries']);
        Permission::firstOrCreate(['name' => 'create deliveries']);
        Permission::firstOrCreate(['name' => 'edit deliveries']);
        Permission::firstOrCreate(['name' => 'manage deliveries']);

        // Stock Alerts
        Permission::firstOrCreate(['name' => 'view stock alerts']);
        Permission::firstOrCreate(['name' => 'manage stock alerts']);

        // Transfers
        Permission::firstOrCreate(['name' => 'view transfers']);
        Permission::firstOrCreate(['name' => 'create transfers']);
        Permission::firstOrCreate(['name' => 'manage transfers']);

        // Wastes
        Permission::firstOrCreate(['name' => 'view wastes']);
        Permission::firstOrCreate(['name' => 'manage wastes']);

        // Work Stages
        Permission::firstOrCreate(['name' => 'view work stages']);
        Permission::firstOrCreate(['name' => 'manage work stages']);

        // Approvals
        Permission::firstOrCreate(['name' => 'view approvals']);
        Permission::firstOrCreate(['name' => 'manage approvals']);
    }
}