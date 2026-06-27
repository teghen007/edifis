<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Domain\Academics\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClassSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'classSubjects';

    protected static ?string $title = 'Subjects';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('subject_id')
                ->label('Subject')
                ->options(fn () => Subject::where('active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    // Auto-suggest the class-specific code, e.g. GEO 1 / GEO US.
                    $subject = Subject::find($state);
                    if ($subject) {
                        $suffix = $this->getOwnerRecord()->codeSuffix();
                        $set('code', trim($subject->code . ' ' . $suffix));
                        $set('coefficient', $subject->coefficient ?? 1);
                    }
                }),
            Forms\Components\TextInput::make('code')
                ->label('Class code')
                ->helperText('Class-specific label, e.g. GEO 1 for Form 1, GEO US for Upper Sixth.')
                ->required()
                ->maxLength(32),
            Forms\Components\TextInput::make('coefficient')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Class code')->badge(),
                Tables\Columns\TextColumn::make('coefficient')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add subject'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No subjects yet')
            ->emptyStateDescription('Add the subjects this class takes — they cascade to every section.');
    }
}
