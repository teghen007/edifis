<?php

namespace App\Filament\Resources;

use App\Domain\Students\Actions\EnrolStudent;
use App\Domain\Students\Models\Student;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'People';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['secretary', 'bursar', 'principal', 'school_admin']);
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
                    Forms\Components\Select::make('boarding_status')->label('Boarder / Day')
                        ->options(['day' => 'Day student', 'boarding' => 'Boarder'])->default('day'),
                    \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Student photo')
                        ->collection('photo')
                        ->image()
                        ->imageEditor()
                        ->avatar(),
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
                Tables\Columns\TextColumn::make('boarding_status')
                    ->label('Boarding')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'boarding' ? 'Boarder' : 'Day')
                    ->color(fn ($state) => $state === 'boarding' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('enrolled_at')->dateTime(),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_class_id')
                    ->label('Class')
                    ->options(fn () => \App\Domain\Academics\Models\SchoolClass::orderBy('level')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('boarding_status')
                    ->label('Boarding')
                    ->options(['day' => 'Day students', 'boarding' => 'Boarders']),
                Tables\Filters\SelectFilter::make('sex')
                    ->options(['M' => 'Male', 'F' => 'Female']),
                Tables\Filters\TernaryFilter::make('active')->default(true),
            ])
            ->defaultSort('family_name')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('idCard')
                    ->label('ID Card')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->action(function (Student $record) {
                        $pdf = app(\App\Domain\Students\Support\IdCardRenderer::class)->render($record);

                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'id-card-' . ($record->master_pea_id ?: $record->id) . '.pdf',
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubjectsRelationManager::class,
        ];
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
