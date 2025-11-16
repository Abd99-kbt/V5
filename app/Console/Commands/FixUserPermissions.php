<?php

namespace App\Console\Commands;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;

class FixUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:user-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix user permissions and roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting user permissions fix...');

        // Get the general manager role
        $generalManagerRole = Role::where('name', 'مدير_شامل')->first();
        if (!$generalManagerRole) {
            $this->error('General Manager role not found!');
            return 1;
        }

        $this->info('Found General Manager role: ' . $generalManagerRole->name);

        // Fix admin user
        $adminUser = User::where('username', 'admin')->first();
        if ($adminUser) {
            $this->info('Found admin user: ' . $adminUser->name);
            $adminUser->syncRoles([$generalManagerRole]);
            $this->info('Admin user role synced successfully!');
            
            if ($adminUser->can('manage users')) {
                $this->info('Admin user has "manage users" permission!');
            } else {
                $this->error('Admin user does NOT have "manage users" permission - this needs to be fixed!');
            }
        } else {
            $this->error('Admin user not found!');
        }

        // Fix general manager user
        $generalManagerUser = User::where('username', 'مدير_شامل')->first();
        if ($generalManagerUser) {
            $this->info('Found general manager user: ' . $generalManagerUser->name);
            
            if ($generalManagerUser->can('manage users')) {
                $this->info('General manager user has "manage users" permission!');
            } else {
                $this->info('General manager user does NOT have "manage users" permission - syncing roles...');
                $generalManagerUser->syncRoles([$generalManagerRole]);
                $this->info('General manager user role synced successfully!');
            }
        } else {
            $this->error('General manager user not found!');
        }

        $this->info('\\n=== User Permissions Summary ===');

        $allUsers = User::all();
        foreach ($allUsers as $user) {
            $roles = $user->getRoleNames()->implode(', ');
            $hasManageUsers = $user->can('manage users') ? 'Yes' : 'No';
            $this->line("User: " . $user->username . " | Roles: " . $roles . " | Can manage users: " . $hasManageUsers);
        }

        $this->info('\\nUser permissions fix completed!');

        return 0;
    }
}