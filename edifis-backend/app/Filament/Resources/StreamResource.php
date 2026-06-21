<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\Stream;
use App\Filament\Resources\StreamResource\Pages;
use App\Filament\Resources\StreamResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StreamResource extends Resource
{
    protected static ?string $model = Stream::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Classes & Streams';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['school_admin', 'principal']);
    }

    public static function form(Form $form): Form
    {
        $staffOptions = User::role(['class_master', 'subject_teacher'])->pluck('name', 'id');

        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('class_id')
                ->relationship('schoolClass', 'name')->required(),
            Forms\Components\Select::make('section_id')
                ->relationship('section', 'name')->required(),
            Forms\Components\Select::make('academic_year_id')
                ->relationship('academicYear', 'name')->required(),
            Forms\Components\Toggle::make('active')->default(true),
            Forms\Components\Select::make('class_master_id')
                ->label('Class Master')
                ->options($staffOptions)
                ->searchable()
                ->afterStateHydrated(function ($component, $state, $record) {
                    if ($record) {
                        $cm = DB::table('class_masters')->where('stream_id', $record->id)->first();
                        $component->state($cm->teacher_id ?? null);
                    }
                })
                ->dehydrated(false)
                ->saveRelationshipsUsing(function ($state, $record) {
                    DB::table('class_masters')->where('stream_id', $record->id)->delete();
                    if ($state) {
                        DB::table('class_masters')->insert([
                            'teacher_id' => $state,
                            'stream_id' => $record->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('schoolClass.name'),
                Tables\Columns\TextColumn::make('section.name'),
                Tables\Columns\TextColumn::make('academicYear.name'),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubjectsRelationManager::class,
            RelationManagers\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStreams::route('/'),
            'create' => Pages\CreateStream::route('/create'),
            'edit' => Pages\EditStream::route('/{record}/edit'),
        ];
    }
}
