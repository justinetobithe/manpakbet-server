<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControl
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_USER = 'user';

    public const PROTECTED_ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_USER];

    public const BACKOFFICE_ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF];

    public const PERMISSIONS = [
        'dashboard.view' => 'View the admin dashboard',
        'users.view' => 'View players and staff',
        'users.manage' => 'Create, edit and suspend users',
        'users.balance' => 'Adjust player balances',
        'games.manage' => 'Manage games, categories and providers',
        'content.manage' => 'Manage banners, promotions and news',
        'transactions.view' => 'View wallet transactions',
        'transactions.process' => 'Approve or reject deposits and withdrawals',
        'roles.manage' => 'Manage roles and permissions',
        'settings.manage' => 'Manage site, payment and game settings',
        'kyc.review' => 'Review player identity verification',
    ];

    public const DEFAULT_ROLE_PERMISSIONS = [
        self::ROLE_ADMIN => ['*'],
        self::ROLE_STAFF => [
            'dashboard.view',
            'users.view',
            'content.manage',
            'transactions.view',
            'transactions.process',
            'kyc.review',
        ],
        self::ROLE_USER => [],
    ];

    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::DEFAULT_ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($permissions === ['*']) {
                $role->syncPermissions(array_keys(self::PERMISSIONS));
            } elseif ($role->permissions()->count() === 0 && $permissions) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
