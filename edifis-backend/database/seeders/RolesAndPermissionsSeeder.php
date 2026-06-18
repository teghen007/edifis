<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** Eight staff roles + parent. Per ADR-013, no student role. */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = $this->definePermissions();
        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = $this->defineRoles();
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
        }
    }

    private function definePermissions(): array
    {
        return [
            'vacuum.query',
            'vacuum.command',
            'promotion.override',
            'account.deactivate',
            'timetable.manage',
            'calendar.manage',
            'academics.view.school',
            'attendance.view.school',
            'discipline.all',
            'issuance.all',
            'fees.all',
            'students.register',
            'signature.capture',
            'documents.print',
            'marks.lock.edit',
            'attendance.take',
            'marks.coordinate',
            'discipline.record',
            'documents.print.ownClass',
            'marks.enter',
            'attendance.own.classes',
            'documents.print.ownClasses',
            'exeat.all',
            'students.enrol',
            'demographics.edit',
            'child.balance.view',
            'child.results.view',
            'child.attendance.view',
            'child.documents.download',
            'onboarding.approve',
            'onboarding.list',
            'tenancy.manage',
        ];
    }

    private function defineRoles(): array
    {
        return [
            'principal' => [
                'vacuum.query',
                'vacuum.command',
                'promotion.override',
                'account.deactivate',
                'academics.view.school',
                'attendance.view.school',
                'documents.print',
            ],
            'vice_principal' => [
                'timetable.manage',
                'calendar.manage',
                'academics.view.school',
                'attendance.view.school',
                'discipline.all',
                'documents.print',
            ],
            'bursar' => [
                'issuance.all',
                'fees.all',
                'students.register',
                'signature.capture',
                'documents.print',
            ],
            'class_master' => [
                'attendance.take',
                'marks.coordinate',
                'discipline.record',
                'documents.print.ownClass',
            ],
            'subject_teacher' => [
                'marks.enter',
                'attendance.own.classes',
                'documents.print.ownClasses',
            ],
            'discipline_master' => [
                'discipline.all',
                'exeat.all',
                'attendance.view.school',
            ],
            'secretary' => [
                'students.enrol',
                'demographics.edit',
                'documents.print',
            ],
            'parent' => [
                'child.balance.view',
                'child.results.view',
                'child.attendance.view',
                'child.documents.download',
            ],
            'pea_admin' => [
                'onboarding.approve',
                'onboarding.list',
                'tenancy.manage',
            ],
        ];
    }
}
