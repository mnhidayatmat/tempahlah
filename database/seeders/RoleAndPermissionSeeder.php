<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $permissions = [
            'tenant.manage', 'tenant.view',
            'subscription.manage',
            'staff.invite', 'staff.remove', 'staff.view',
            'properties.create', 'properties.update', 'properties.delete', 'properties.view',
            'rooms.create', 'rooms.update', 'rooms.delete', 'rooms.view',
            'pricing.create', 'pricing.update', 'pricing.delete',
            'calendar.manage', 'calendar.view',
            'bookings.create', 'bookings.update', 'bookings.cancel', 'bookings.view',
            'bookings.checkin', 'bookings.checkout',
            'payments.mark_paid', 'payments.refund', 'payments.view',
            'invoices.create', 'invoices.send', 'invoices.template_manage', 'invoices.view',
            'reports.view', 'reports.export',
            'reviews.respond', 'reviews.view',
            'incidents.report', 'incidents.view',
            'cleaning.assign', 'cleaning.update_own', 'cleaning.view',
            'laundry.assign', 'laundry.update_own', 'laundry.view',
            'maintenance.manage', 'maintenance.view',
            'integrations.manage',
            'marketplace.toggle',
            'api.access',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'owner' => $permissions,
            'manager' => [
                'tenant.view', 'staff.view',
                'properties.create', 'properties.update', 'properties.view',
                'rooms.create', 'rooms.update', 'rooms.view',
                'pricing.create', 'pricing.update',
                'calendar.manage', 'calendar.view',
                'bookings.create', 'bookings.update', 'bookings.cancel', 'bookings.view',
                'bookings.checkin', 'bookings.checkout',
                'payments.mark_paid', 'payments.view',
                'invoices.create', 'invoices.send', 'invoices.view',
                'reports.view',
                'reviews.respond', 'reviews.view',
                'incidents.report', 'incidents.view',
                'cleaning.assign', 'cleaning.view',
                'laundry.assign', 'laundry.view',
                'maintenance.manage', 'maintenance.view',
                'integrations.manage',
            ],
            'cleaner' => [
                'cleaning.update_own', 'cleaning.view',
            ],
            'laundry' => [
                'laundry.update_own', 'laundry.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
