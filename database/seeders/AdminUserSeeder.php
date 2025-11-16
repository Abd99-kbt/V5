<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. General Management Stage - مدير_شامل (General Manager)
        $generalManager = User::firstOrCreate(
            ['username' => 'مدير_شامل'],
            [
                'name' => 'أحمد محمد السعيد - مدير شامل',
                'email' => 'general.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $generalManagerRole = Role::where('name', 'مدير_شامل')->first();
        if ($generalManagerRole) {
            $generalManager->assignRole($generalManagerRole);
        }

        // 2. Order Creation & Review Stage - موظف_مبيعات
        $salesEmployee = User::firstOrCreate(
            ['username' => 'موظف_مبيعات'],
            [
                'name' => 'فاطمة أحمد علي - موظف مبيعات',
                'email' => 'sales.employee@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $salesEmployeeRole = Role::where('name', 'موظف_مبيعات')->first();
        if ($salesEmployeeRole) {
            $salesEmployee->assignRole($salesEmployeeRole);
        }

        $salesManager = User::firstOrCreate(
            ['username' => 'مدير_مبيعات'],
            [
                'name' => 'محمد عبدالله الخطيب - مدير مبيعات',
                'email' => 'sales.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $salesManagerRole = Role::where('name', 'مدير_مبيعات')->first();
        if ($salesManagerRole) {
            $salesManager->assignRole($salesManagerRole);
        }

        // 3. Material Reservation Stage - مسؤول_مستودع
        $warehouseManager = User::firstOrCreate(
            ['username' => 'مسؤول_مستودع'],
            [
                'name' => 'سعد حسن الأحمد - مسؤول مستودع',
                'email' => 'warehouse.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $warehouseManagerRole = Role::where('name', 'مسؤول_مستودع')->first();
        if ($warehouseManagerRole) {
            $warehouseManager->assignRole($warehouseManagerRole);
        }

        $warehouseEmployee = User::firstOrCreate(
            ['username' => 'موظف_مستودع'],
            [
                'name' => 'خالد محمود الزهراني - موظف مستودع',
                'email' => 'warehouse.employee@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $warehouseEmployeeRole = Role::where('name', 'موظف_مستودع')->first();
        if ($warehouseEmployeeRole) {
            $warehouseEmployee->assignRole($warehouseEmployeeRole);
        }

        // 4. Sorting Stage - مسؤول_فرازة (Sorting Manager)
        $sortingManager = User::firstOrCreate(
            ['username' => 'مسؤول_فرازة'],
            [
                'name' => 'عبدالرحمن محمد النجار - مسؤول فرازة',
                'email' => 'sorting.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $sortingManagerRole = Role::where('name', 'مسؤول_فرازة')->first();
        if ($sortingManagerRole) {
            $sortingManager->assignRole($sortingManagerRole);
        }

        // 5. Cutting Stage - مسؤول_قصاصة (Cutting Manager)
        $cuttingManager = User::firstOrCreate(
            ['username' => 'مسؤول_قصاصة'],
            [
                'name' => 'يوسف علي الأيوبي - مسؤول قصاصة',
                'email' => 'cutting.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $cuttingManagerRole = Role::where('name', 'مسؤول_قصاصة')->first();
        if ($cuttingManagerRole) {
            $cuttingManager->assignRole($cuttingManagerRole);
        }

        // 6. Invoicing Stage - محاسب (Accountant)
        $accountant = User::firstOrCreate(
            ['username' => 'محاسب'],
            [
                'name' => 'نور الدين حسن المصري - محاسب',
                'email' => 'accountant@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $accountantRole = Role::where('name', 'محاسب')->first();
        if ($accountantRole) {
            $accountant->assignRole($accountantRole);
        }

        // 7. Delivery Stage - مسؤول_تسليم (Delivery Manager)
        $deliveryManager = User::firstOrCreate(
            ['username' => 'مسؤول_تسليم'],
            [
                'name' => 'زياد محمد المقدسي - مسؤول تسليم',
                'email' => 'delivery.manager@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $deliveryManagerRole = Role::where('name', 'مسؤول_تسليم')->first();
        if ($deliveryManagerRole) {
            $deliveryManager->assignRole($deliveryManagerRole);
        }

        // Keep the original admin for backward compatibility with username
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@admin.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        $adminRole = Role::where('name', 'مدير_شامل')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }
    }
}
