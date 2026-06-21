<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffUserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'People';
    protected static ?string $label = 'Staff User';
    protected static ?string $pluralLabel = 'Staff Users';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $staffRoles = ['principal', 'vice_principal', 'bursar', 'class_master', 'subject_teacher', 'discipline_master', 'secretary', 'school_admin'];
        return parent::getEloquentQuery()->role($staffRoles);
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
            Forms\Components\Select::make('roles')
                ->label('Role')
                ->relationship('roles', 'name')
                ->options(function () {
                    return \Spatie\Permission\Models\Role::whereIn('name', [
                        'principal', 'vice_principal', 'bursar', 'class_master',
                        'subject_teacher', 'discipline_master', 'secretary', 'school_admin',
                    ])->pluck('name', 'id');
                })
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->badge(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
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
