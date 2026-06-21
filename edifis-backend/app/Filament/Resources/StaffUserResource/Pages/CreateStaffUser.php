<?php

namespace App\Filament\Resources\StaffUserResource\Pages;

use App\Filament\Resources\StaffUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;
        $roleName = $this->data['roles'] ?? null;

        if (!$roleName) {
            return;
        }

        $roleName = is_array($roleName) ? ($roleName[0] ?? null) : $roleName;
        if (!$roleName) {
            return;
        }

        $user->syncRoles([]);

        foreach (['web', 'sanctum'] as $guard) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->id,
            ]);
        }
    }
}
