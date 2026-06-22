<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\School\Models\SchoolSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SchoolSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $title = 'School Settings';
    protected static string $view = 'filament.pages.school-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['school_admin', 'principal']) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(SchoolSetting::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identity')->schema([
                    TextInput::make('name')->label('School name')->required()->maxLength(255),
                    Select::make('school_type')->label('School type')->required()
                        ->options(['day' => 'Day', 'boarding' => 'Boarding', 'both' => 'Both (Day & Boarding)']),
                    TextInput::make('motto')->maxLength(255),
                    TextInput::make('principal_name')->label('Principal name')->maxLength(255),
                    TextInput::make('logo_url')->label('Logo URL')->url()->maxLength(500)
                        ->helperText('Paste a hosted image URL (e.g. https://...). No file upload.'),
                ])->columns(2),
                Section::make('Contact')->schema([
                    TextInput::make('phone')->tel()->maxLength(50),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('currency')->default('XAF')->maxLength(10),
                    Textarea::make('address')->rows(2)->columnSpanFull(),
                ])->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SchoolSetting::current()->update($this->form->getState());
        SchoolSetting::flush();

        Notification::make()->title('School settings saved')->success()->send();
    }
}
