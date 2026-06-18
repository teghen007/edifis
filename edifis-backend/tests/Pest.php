<?php

use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Ramsey\Uuid\Uuid;

uses(TestCase::class)->in('Feature', 'Unit');

if (! function_exists('tid')) {
    /**
     * Deterministic test UUID. The SAME semantic key always returns the SAME valid
     * UUID (v5); distinct keys return distinct UUIDs. Use this for every hard-coded
     * fixture id so cross-references stay consistent and never drift again — e.g.
     *   $item = CatalogueItem::create(['id' => tid('cat.math'), ...]);
     *   IssueEvent::create(['catalogue_item_id' => tid('cat.math'), ...]); // matches
     * Replaces hand-typed UUID literals, which a batch find/replace can silently corrupt.
     */
    function tid(string $key): string
    {
        // Fixed namespace (standard DNS namespace UUID) + the key => stable v5 UUID.
        return Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "edifis:test:{$key}")->toString();
    }
}

uses()->beforeEach(function () {
    $roles = [
        'principal', 'vice_principal', 'bursar', 'class_master',
        'subject_teacher', 'discipline_master', 'secretary', 'parent',
        'pea_admin',
    ];

    foreach ($roles as $roleName) {
        Role::findOrCreate($roleName);
    }
})->in('Feature');
