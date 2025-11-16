<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing users with Arabic usernames
        $userUpdates = [
            // Update existing users with role-based Arabic usernames
            ['email' => 'general.manager@example.com', 'username' => 'مدير_شامل'],
            ['email' => 'sales.employee@example.com', 'username' => 'موظف_مبيعات'],
            ['email' => 'sales.manager@example.com', 'username' => 'مدير_مبيعات'],
            ['email' => 'warehouse.manager@example.com', 'username' => 'مسؤول_مستودع'],
            ['email' => 'warehouse.employee@example.com', 'username' => 'موظف_مستودع'],
            ['email' => 'sorting.manager@example.com', 'username' => 'مسؤول_فرازة'],
            ['email' => 'cutting.manager@example.com', 'username' => 'مسؤول_قصاصة'],
            ['email' => 'accountant@example.com', 'username' => 'محاسب'],
            ['email' => 'delivery.manager@example.com', 'username' => 'مسؤول_تسليم'],
            ['email' => 'admin@admin.com', 'username' => 'admin'],
        ];

        foreach ($userUpdates as $update) {
            User::where('email', $update['email'])->update(['username' => $update['username']]);
        }

        // Update other existing users that might not have usernames
        User::whereNull('username')->whereNotNull('email')->chunk(100, function ($users) {
            foreach ($users as $user) {
                $username = str_replace([' ', '@', '.'], '_', $user->email);
                $user->update(['username' => $username]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove usernames from users (set them back to null)
        User::whereIn('username', [
            'مدير_شامل',
            'موظف_مبيعات',
            'مدير_مبيعات',
            'مسؤول_مستودع',
            'موظف_مستودع',
            'مسؤول_فرازة',
            'مسؤول_قصاصة',
            'محاسب',
            'مسؤول_تسليم',
            'admin',
        ])->update(['username' => null]);
    }
};