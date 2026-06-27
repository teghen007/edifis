<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $label = 'Activity Log';
    protected static ?string $pluralLabel = 'Activity Log';
    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('causer.name')->label('By')->default('system')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->infolist([
                    TextEntry::make('event')->badge(),
                    TextEntry::make('subject_type')->label('Record')->formatStateUsing(fn ($s) => $s ? class_basename($s) : '—'),
                    TextEntry::make('causer.name')->label('Changed by')->default('system'),
                    TextEntry::make('created_at')->dateTime(),
                    KeyValueEntry::make('properties.attributes')->label('New values'),
                    KeyValueEntry::make('properties.old')->label('Previous values'),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
