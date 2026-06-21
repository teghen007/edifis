<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use App\Filament\Resources\TeacherAssignmentResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TeacherAssignmentResource extends Resource
{
    protected static ?string $model = null;
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
            Forms\Components\Select::make('subject_id')
                ->label('Subject')
                ->options(Subject::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('stream_id')
                ->label('Stream')
                ->options(Stream::with('schoolClass')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name . ' (' . ($s->schoolClass?->name ?? '') . ')']))
                ->searchable()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(DB::table('teacher_assignments')
                ->join('users', 'teacher_assignments.teacher_id', '=', 'users.id')
                ->join('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
                ->join('streams', 'teacher_assignments.stream_id', '=', 'streams.id')
                ->select('teacher_assignments.*', 'users.name as teacher_name', 'subjects.name as subject_name', 'streams.name as stream_name'))
            ->columns([
                Tables\Columns\TextColumn::make('teacher_name')->label('Teacher')->searchable(),
                Tables\Columns\TextColumn::make('subject_name')->label('Subject')->searchable(),
                Tables\Columns\TextColumn::make('stream_name')->label('Stream')->searchable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->action(function ($record) {
                    DB::table('teacher_assignments')
                        ->where('teacher_id', $record->teacher_id)
                        ->where('subject_id', $record->subject_id)
                        ->where('stream_id', $record->stream_id)
                        ->delete();
                }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAssignments::route('/'),
            'create' => Pages\CreateTeacherAssignment::route('/create'),
        ];
    }
}
