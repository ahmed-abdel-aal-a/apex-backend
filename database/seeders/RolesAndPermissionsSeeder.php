<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Temporarily set default guard to sanctum for the seeder
        config(['auth.defaults.guard' => 'sanctum']);

        // create permissions
        // Staff/Admin Permissions
        $permStaff = Permission::findOrCreate('manage staff', 'sanctum');
        $permRoles = Permission::findOrCreate('manage roles', 'sanctum');
        
        // Product Permissions
        $permProducts = Permission::findOrCreate('manage products', 'sanctum');
        
        // Orders Permissions
        $permOrders = Permission::findOrCreate('manage orders', 'sanctum');

        // Settings Permissions
        $permSettings = Permission::findOrCreate('manage settings', 'sanctum');

        // create roles and assign created permissions
        $roleEditor = Role::findOrCreate('Editor', 'sanctum');
        $roleEditor->syncPermissions([$permProducts, $permOrders]);

        $roleManager = Role::findOrCreate('Manager', 'sanctum');
        $roleManager->syncPermissions([$permProducts, $permOrders, $permSettings]);

        // Super Admin Role
        $roleSuperAdmin = Role::findOrCreate('Super Admin', 'sanctum');
        // Super Admin gets all permissions implicitly via Gate in AuthServiceProvider, 
        // but we can assign all for completeness.
        $roleSuperAdmin->syncPermissions([$permStaff, $permRoles, $permProducts, $permOrders, $permSettings]);

        // Create Super Admin User
        $user = User::firstOrCreate([
            'email' => 'admin@apex-store.test',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($roleSuperAdmin);
    }
}
