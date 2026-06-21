<?php

namespace App\Filament\Resources;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Filament\Resources\FeeResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeeResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['bursar', 'principal']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student_id')->searchable(),
                Tables\Columns\TextColumn::make('source_event_id')->label('Source'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('XAF')
                    ->formatStateUsing(fn ($state) => number_format((int) $state) . ' XAF'),
                Tables\Columns\TextColumn::make('posted_at')->dateTime(),
            ])
            ->defaultSort('posted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFees::route('/'),
        ];
    }
}
