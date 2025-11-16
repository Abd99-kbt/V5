<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing users with appropriate roles
        $users = User::all();

        foreach ($users as $user) {
            // Skip if user already has a role
            if ($user->getRoleNames()->isNotEmpty()) {
                continue;
            }

            // For now, assign a default role based on email or other criteria
            // Since we don't have specific access patterns, we'll assign based on email
            if (str_contains($user->email, 'admin')) {
                $role = Role::where('name', 'مدير_شامل')->first();
            } elseif (str_contains($user->email, 'sales') || str_contains($user->email, 'موظف')) {
                $role = Role::where('name', 'موظف_مبيعات')->first();
            } elseif (str_contains($user->email, 'accountant') || str_contains($user->email, 'محاسب')) {
                $role = Role::where('name', 'محاسب')->first();
            } elseif (str_contains($user->email, 'warehouse') || str_contains($user->email, 'مستودع')) {
                $role = Role::where('name', 'أمين_مستودع')->first();
            } elseif (str_contains($user->email, 'order') || str_contains($user->email, 'طلبات')) {
                $role = Role::where('name', 'متابع_طلبات')->first();
            } elseif (str_contains($user->email, 'delivery') || str_contains($user->email, 'تسليم')) {
                $role = Role::where('name', 'مسؤول_تسليم')->first();
            } elseif (str_contains($user->email, 'cutting') || str_contains($user->email, 'قصاصة')) {
                $role = Role::where('name', 'مسؤول_قصاصة')->first();
            } elseif (str_contains($user->email, 'sorting') || str_contains($user->email, 'فرازة')) {
                $role = Role::where('name', 'مسؤول_فرازة')->first();
            } else {
                // Default role for other users
                $role = Role::where('name', 'موظف_مبيعات')->first();
            }

            if ($role) {
                $user->assignRole($role);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all roles from users (optional rollback)
        $users = User::all();
        foreach ($users as $user) {
            $user->syncRoles([]);
        }
    }
};