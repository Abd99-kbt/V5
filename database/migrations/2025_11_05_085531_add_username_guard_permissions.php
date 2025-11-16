<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissionsToAdd = [
            'view users',
            'create users', 
            'edit users',
            'delete users',
            'manage users',
            'view materials',
            'create materials',
            'edit materials',
            'delete materials', 
            'manage materials',
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'manage orders',
            'process orders',
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'manage invoices',
            'view warehouses',
            'create warehouses',
            'edit warehouses',
            'delete warehouses',
            'manage warehouses',
            'manage stock',
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            'manage customers',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',
            'manage suppliers',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'manage categories',
            'view reports',
            'generate reports',
            'export reports',
            'view deliveries',
            'create deliveries',
            'edit deliveries',
            'manage deliveries',
            'view stock alerts',
            'manage stock alerts',
            'view transfers',
            'create transfers',
            'manage transfers',
            'view wastes',
            'manage wastes',
            'view work stages',
            'manage work stages',
            'view approvals',
            'manage approvals',
        ];

        foreach ($permissionsToAdd as $permissionName) {
            Permission::updateOrCreate(
                [
                    'name' => $permissionName,
                    'guard_name' => 'username'
                ],
                [
                    'guard_name' => 'username'
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions for username guard
        Permission::where('guard_name', 'username')->delete();
    }
};