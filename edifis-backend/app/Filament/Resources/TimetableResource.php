<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimetableResource\Pages;
use App\Filament\Resources\TimetableResource\RelationManagers;
use App\Domain\Timetable\Models\TimetableEntry as Timetable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TimetableResource extends Resource
{
    protected static ?string $model = Timetable::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Assignments';
    protected static ?string $label = 'Timetable Entry';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn ($state) => Timetable::DAYS[(int) $state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => "{$record->period_start} – {$record->period_end}"),
                Tables\Columns\TextColumn::make('schoolClass.name')->label('Class')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('teacher.name')->label('Teacher')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('room')->toggleable(),
                Tables\Columns\IconColumn::make('is_approved')->label('Approved')->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->filters([
                Tables\Filters\SelectFilter::make('day_of_week')->label('Day')->options(Timetable::DAYS),
                Tables\Filters\TernaryFilter::make('is_approved')->label('Approved'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimetables::route('/'),
            'create' => Pages\CreateTimetable::route('/create'),
            'edit' => Pages\EditTimetable::route('/{record}/edit'),
        ];
    }
}
