<?php

namespace App\Filament\Resources;

use App\Domain\Students\Actions\EnrolStudent;
use App\Domain\Students\Models\Student;
use App\Filament\Resources\StudentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Academic';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['secretary', 'bursar', 'principal']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Student Details')
                ->schema([
                    Forms\Components\TextInput::make('given_name')->required(),
                    Forms\Components\TextInput::make('family_name')->required(),
                    Forms\Components\TextInput::make('other_names'),
                    Forms\Components\Select::make('sex')->options(['M' => 'Male', 'F' => 'Female']),
                    Forms\Components\DatePicker::make('date_of_birth'),
                    Forms\Components\TextInput::make('current_class_id')->label('Class ID')->required(),
                    Forms\Components\FileUpload::make('photo_ref')->disk('public'),
                ]),
            Forms\Components\Section::make('Consent (required for minors)')
                ->schema([
                    Forms\Components\TextInput::make('consent.consenter_name')->label('Guardian Name')->required(),
                    Forms\Components\Select::make('consent.relationship')
                        ->options(['mother' => 'Mother', 'father' => 'Father', 'guardian' => 'Guardian', 'other' => 'Other'])
                        ->required(),
                    Forms\Components\TextInput::make('consent.consenter_contact')->label('Guardian Contact'),
                    Forms\Components\CheckboxList::make('consent.scope')
                        ->options([
                            'academic_records' => 'Academic Records',
                            'photo_on_id' => 'Photo on ID Card',
                            'parent_portal' => 'Parent Portal Access',
                        ])
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('master_pea_id')->label('PEA ID')->searchable(),
                Tables\Columns\TextColumn::make('given_name')->searchable(),
                Tables\Columns\TextColumn::make('family_name')->searchable(),
                Tables\Columns\TextColumn::make('sex'),
                Tables\Columns\TextColumn::make('enrolled_at')->dateTime(),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
        ];
    }
}
