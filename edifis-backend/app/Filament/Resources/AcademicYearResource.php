<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\AcademicYear;
use App\Filament\Resources\AcademicYearResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Academic';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Toggle::make('is_current'),
            Forms\Components\DatePicker::make('starts_on'),
            Forms\Components\DatePicker::make('ends_on'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_current')->boolean(),
                Tables\Columns\TextColumn::make('starts_on')->date(),
                Tables\Columns\TextColumn::make('ends_on')->date(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicYears::route('/'),
            'create' => Pages\CreateAcademicYear::route('/create'),
            'edit' => Pages\EditAcademicYear::route('/{record}/edit'),
        ];
    }
}
