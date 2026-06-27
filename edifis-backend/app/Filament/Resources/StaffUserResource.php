<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffUserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'People';
    protected static ?string $label = 'Staff User';
    protected static ?string $pluralLabel = 'Staff Users';

    public const STAFF_ROLES = [
        'principal', 'vice_principal', 'bursar', 'class_master',
        'subject_teacher', 'discipline_master', 'secretary', 'school_admin',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->role(self::STAFF_ROLES);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required(fn ($context) => $context === 'create')
                ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state)),
            // Role is stored by NAME and synced across both guards (web + sanctum)
            // in the page hooks — see Pages\Create/EditStaffUser.
            Forms\Components\Select::make('role')
                ->label('Role')
                ->options(collect(self::STAFF_ROLES)->mapWithKeys(fn ($r) => [$r => Str::headline($r)]))
                ->required()
                ->dehydrated(false),
        ]);
    }

    /** Set the user's role to exactly this one, under both web and sanctum guards. */
    public static function syncStaffRole(User $user, ?string $roleName): void
    {
        if (! $roleName || ! in_array($roleName, self::STAFF_ROLES, true)) {
            return;
        }

        $roleIds = Role::where('name', $roleName)
            ->whereIn('guard_name', ['web', 'sanctum'])
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Str::headline($state))
                    ->getStateUsing(fn (User $record) => $record->roles->whereIn('name', self::STAFF_ROLES)->pluck('name')->unique()->values()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->required()
                            ->minLength(6),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['password' => Hash::make($data['password'])]);
                        Notification::make()->title('Password reset for ' . $record->name)->success()->send();
                    }),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffUsers::route('/'),
            'create' => Pages\CreateStaffUser::route('/create'),
            'edit' => Pages\EditStaffUser::route('/{record}/edit'),
        ];
    }
}
