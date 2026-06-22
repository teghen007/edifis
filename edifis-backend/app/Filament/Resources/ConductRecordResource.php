<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Term;
use App\Domain\Conduct\Models\ConductRecord;
use App\Domain\Students\Models\Student;
use App\Filament\Resources\ConductRecordResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConductRecordResource extends Resource
{
    protected static ?string $model = ConductRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Discipline';
    protected static ?string $modelLabel = 'Conduct record';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['discipline_master', 'principal', 'vice_principal', 'school_admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('student_id')
                ->label('Student')->required()->searchable()
                ->options(fn () => Student::where('active', true)->get()
                    ->mapWithKeys(fn ($s) => [$s->id => trim($s->given_name . ' ' . $s->family_name)])),
            Forms\Components\Select::make('term_id')
                ->label('Term')->required()
                ->options(fn () => Term::pluck('name', 'id')),
            Forms\Components\Select::make('stream_id')
                ->label('Class')
                ->options(fn () => Stream::pluck('name', 'id')),
            Forms\Components\Select::make('conduct_grade')
                ->label('Conduct')->required()
                ->options(['Excellent' => 'Excellent', 'Good' => 'Good', 'Fair' => 'Fair', 'Poor' => 'Poor']),
            Forms\Components\TextInput::make('punctuality')->maxLength(50),
            Forms\Components\Textarea::make('comment')->rows(2)->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('student'))
            ->columns([
                Tables\Columns\TextColumn::make('student_id')->label('Student')
                    ->getStateUsing(fn ($record) => trim(($record->student->given_name ?? '') . ' ' . ($record->student->family_name ?? ''))),
                Tables\Columns\TextColumn::make('conduct_grade')->badge(),
                Tables\Columns\TextColumn::make('comment')->limit(40),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConductRecords::route('/'),
            'create' => Pages\CreateConductRecord::route('/create'),
            'edit' => Pages\EditConductRecord::route('/{record}/edit'),
        ];
    }
}
