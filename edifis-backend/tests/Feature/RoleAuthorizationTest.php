<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['edifis.mode' => 'local']);
});

it('all eight roles can be created', function () {
    $expectedRoles = [
        'principal', 'vice_principal', 'bursar', 'class_master',
        'subject_teacher', 'discipline_master', 'secretary', 'parent',
    ];

    foreach ($expectedRoles as $roleName) {
        $role = Role::findOrCreate($roleName);
        expect($role->name)->toBe($roleName);
    }

    $allRoles = Role::all()->pluck('name')->toArray();
    foreach ($expectedRoles as $roleName) {
        expect($allRoles)->toContain($roleName);
    }
});

it('student role is never among the allowed roles', function () {
    $allRoles = Role::all()->pluck('name')->toArray();
    expect($allRoles)->not->toContain('student');
    expect(count($allRoles))->toBe(9);
});
