<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarkResource\Pages;
use App\Filament\Resources\MarkResource\RelationManagers;
use App\Domain\Academics\Models\Mark;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarkResource extends Resource
{
    protected static ?string $model = Mark::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Tables\Columns\TextColumn::make('student.family_name')
                    ->label('Student')
                    ->formatStateUsing(fn ($record) => trim(($record->student?->family_name ?? '') . ' ' . ($record->student?->given_name ?? '')))
                    ->searchable(['family_name', 'given_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sequence')->label('Seq')->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->formatStateUsing(fn ($record) => "{$record->score} / {$record->max_score}"),
                Tables\Columns\TextColumn::make('coefficient'),
                Tables\Columns\IconColumn::make('published')->boolean(),
                Tables\Columns\TextColumn::make('recorded_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('published'),
                Tables\Filters\SelectFilter::make('sequence')
                    ->options([1 => 'Seq 1', 2 => 'Seq 2', 3 => 'Seq 3', 4 => 'Seq 4', 5 => 'Seq 5', 6 => 'Seq 6']),
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => \App\Domain\Academics\Models\Subject::orderBy('name')->pluck('name', 'id')),
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
            'index' => Pages\ListMarks::route('/'),
            'create' => Pages\CreateMark::route('/create'),
            'edit' => Pages\EditMark::route('/{record}/edit'),
        ];
    }
}
