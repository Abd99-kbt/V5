<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ArabicUsernameAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function users_can_login_with_arabic_usernames()
    {
        // Create test user with Arabic username
        $user = User::factory()->create([
            'username' => 'مدير_شامل',
            'password' => Hash::make('password123'),
        ]);

        // Test login attempt using Filament login route
        $response = $this->post('/admin/login', [
            'login' => 'مدير_شامل',
            'password' => 'password123',
        ]);

        // Should redirect to Filament dashboard
        $response->assertRedirect('/admin');

        // Should be authenticated
        $this->assertAuthenticatedAs($user, 'username');
    }

    /** @test */
    public function users_cannot_login_with_invalid_credentials()
    {
        // Create test user
        $user = User::factory()->create([
            'username' => 'موظف_مبيعات',
            'password' => Hash::make('password123'),
        ]);

        // Test with wrong password using Filament login
        $response = $this->post('/admin/login', [
            'login' => 'موظف_مبيعات',
            'password' => 'wrongpassword',
        ]);

        // Should show error
        $response->assertSessionHasErrors('login');
        $this->assertGuest('username');
    }

    /** @test */
    public function multiple_arabic_usernames_work()
    {
        $testUsernames = [
            'مدير_شامل',
            'موظف_مبيعات',
            'محاسب',
            'مسؤول_مستودع',
            'موظف_مستودع',
        ];

        foreach ($testUsernames as $username) {
            // Create user
            $user = User::factory()->create([
                'username' => $username,
                'password' => Hash::make('password123'),
            ]);

            // Test login using Filament
            $response = $this->post('/admin/login', [
                'login' => $username,
                'password' => 'password123',
            ]);

            // Should succeed
            $response->assertRedirect('/admin');
            $this->assertAuthenticatedAs($user, 'username');

            // Logout
            $this->post('/admin/logout');
            $this->assertGuest('username');
        }
    }

    /** @test */
    public function database_has_arabic_username_users()
    {
        // Create user with Arabic username like our seeder
        User::create([
            'name' => 'أحمد محمد السعيد - مدير شامل',
            'email' => 'general.manager@example.com',
            'username' => 'مدير_شامل',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'فاطمة أحمد علي - موظف مبيعات',
            'email' => 'sales.employee@example.com',
            'username' => 'موظف_مبيعات',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'نور الدين حسن المصري - محاسب',
            'email' => 'accountant@example.com',
            'username' => 'محاسب',
            'password' => Hash::make('password123'),
        ]);

        // Verify users exist
        $this->assertDatabaseHas('users', ['username' => 'مدير_شامل']);
        $this->assertDatabaseHas('users', ['username' => 'موظف_مبيعات']);
        $this->assertDatabaseHas('users', ['username' => 'محاسب']);

        // Test authentication using Filament
        $this->post('/admin/login', [
            'login' => 'مدير_شامل',
            'password' => 'password123',
        ])->assertRedirect('/admin');
    }
}