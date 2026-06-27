<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Domain\Academics\Models\ClassSubject;
use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
            Forms\Components\Toggle::make('is_core')
                ->label('Core subject')
                ->helperText('Off = elective/optional.')
                ->default(true),
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
                Tables\Columns\IconColumn::make('is_core')->label('Core')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add subject'),
                Tables\Actions\Action::make('copyFromClass')
                    ->label('Copy from another class')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('source_class_id')
                            ->label('Copy all subjects from')
                            ->options(fn () => SchoolClass::where('id', '!=', $this->getOwnerRecord()->id)
                                ->orderBy('level')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $target = $this->getOwnerRecord();
                        $copied = 0;

                        ClassSubject::where('class_id', $data['source_class_id'])->with('subject')->get()
                            ->each(function (ClassSubject $src) use ($target, &$copied) {
                                ClassSubject::updateOrCreate(
                                    ['class_id' => $target->id, 'subject_id' => $src->subject_id],
                                    [
                                        'code' => trim(($src->subject?->code ?? '') . ' ' . $target->codeSuffix()),
                                        'coefficient' => $src->coefficient,
                                        'is_core' => $src->is_core,
                                    ],
                                );
                                $copied++;
                            });

                        Notification::make()
                            ->title("Copied {$copied} subjects")
                            ->body('They cascade to every section of this class.')
                            ->success()
                            ->send();
                    }),
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
