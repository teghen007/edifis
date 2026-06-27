<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\ClassSubject;
use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Filament\Resources\TeacherAssignmentResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TeacherAssignmentResource extends Resource
{
    protected static ?string $model = TeacherAssignment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Assignments';
    protected static ?string $label = 'Teacher Assignment';
    protected static ?string $pluralLabel = 'Teacher Assignments';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']);
    }

    public static function form(Form $form): Form
    {
        $staffRoles = ['principal', 'vice_principal', 'bursar', 'class_master', 'subject_teacher', 'discipline_master', 'secretary', 'school_admin'];
        return $form->schema([
            Forms\Components\Select::make('teacher_id')
                ->label('Teacher')
                ->options(User::whereHas('roles', fn ($q) => $q->whereIn('name', $staffRoles))->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('stream_id')
                ->label('Class section')
                ->options(Stream::with('schoolClass')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name . ' (' . ($s->schoolClass?->name ?? '') . ')']))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('subject_id', null)),
            Forms\Components\Select::make('subject_id')
                ->label('Subject')
                ->helperText('Only the subjects this section offers.')
                ->options(function (Forms\Get $get) {
                    $stream = Stream::find($get('stream_id'));
                    if (! $stream) {
                        return [];
                    }

                    return ClassSubject::where('class_id', $stream->class_id)
                        ->with('subject')
                        ->get()
                        ->mapWithKeys(fn ($cs) => [$cs->subject_id => $cs->code . ' — ' . ($cs->subject?->name ?? '')]);
                })
                ->searchable()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')->label('Teacher')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('stream.name')->label('Stream')->searchable()->sortable(),
            ])
            ->actions([Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAssignments::route('/'),
            'create' => Pages\CreateTeacherAssignment::route('/create'),
        ];
    }
}
