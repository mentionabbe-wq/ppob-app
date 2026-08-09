<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Peta peran → izin. Peran baru cukup ditambah di sini. */
    private const ROLES = [
        'super-admin' => ['*'],
        'admin' => [
            'user.view', 'user.manage', 'product.view', 'product.manage',
            'transaction.view', 'transaction.refund', 'transaction.resend',
            'deposit.view', 'deposit.approve', 'report.view', 'setting.manage',
        ],
        'finance' => [
            'transaction.view', 'transaction.refund', 'deposit.view',
            'deposit.approve', 'report.view', 'user.view', 'wallet.adjust',
        ],
        'operator' => ['transaction.view', 'deposit.view', 'product.view', 'user.view'],
        'reseller' => [],
        'user' => [],
    ];

    private const PERMISSIONS = [
        'user.view', 'user.manage', 'product.view', 'product.manage',
        'transaction.view', 'transaction.refund', 'transaction.resend',
        'deposit.view', 'deposit.approve', 'wallet.adjust',
        'report.view', 'setting.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $role->syncPermissions($permissions === ['*'] ? Permission::all() : $permissions);
        }
    }
}
