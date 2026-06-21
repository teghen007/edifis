<?php

namespace App\Filament\Resources\StreamResource\RelationManagers;

use App\Domain\Students\Models\Student;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';
    protected static ?string $title = 'Enrolled Students';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('given_name')
            ->columns([
                Tables\Columns\TextColumn::make('given_name')->searchable(),
                Tables\Columns\TextColumn::make('family_name')->searchable(),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('active', true)),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
