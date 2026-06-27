<?php

namespace App\Filament\Pages;

use App\Domain\Vacuum\Actions\RunQuery;
use App\Domain\Vacuum\Actions\RunCommand;
use App\Domain\Vacuum\Services\VacuumGuard;
use Filament\Forms\Components;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class Vacuum extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.vacuum';

    public ?string $question = null;
    public ?array $queryResult = null;

    public ?string $command = null;
    public ?string $targetType = null;
    public ?string $targetId = null;
    public ?array $payload = [];
    public ?string $reason = null;
    public bool $confirm = false;
    public ?array $commandResult = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['principal']) ?? false;
    }

    public function getTitle(): string
    {
        return 'VACUUM — Principal Command Centre';
    }

    /** Register both forms so Filament initialises them (else the page 500s on render). */
    protected function getForms(): array
    {
        return ['queryForm', 'commandForm'];
    }

    public function queryForm(Form $form): Form
    {
        return $form->schema([
            Components\TextInput::make('question')
                ->label('Ask the AI Co-pilot')
                ->placeholder('e.g. "Who is borderline for promotion in Form 4?"')
                ->required(),
        ]);
    }

    public function commandForm(Form $form): Form
    {
        return $form->schema([
            Components\Select::make('command')
                ->label('Command')
                ->options([
                    'correct_mark' => 'Correct a Mark',
                    'override_promotion' => 'Override Promotion',
                    'deactivate_account' => 'Deactivate Account',
                ])
                ->required(),
            Components\TextInput::make('targetType')
                ->label('Target Type')
                ->helperText('mark, promotion_decision, or account')
                ->required(),
            Components\TextInput::make('targetId')
                ->label('Target ID')
                ->required(),
            Components\KeyValue::make('payload')
                ->label('Command Payload')
                ->keyLabel('Field')
                ->valueLabel('Value'),
            Components\Textarea::make('reason')
                ->label('Reason')
                ->required()
                ->helperText('Mandatory. Every VACUUM action is audited.'),
            Components\Toggle::make('confirm')
                ->label('I confirm this command')
                ->helperText('Required for deactivate_account and bulk commands.')
                ->default(false),
        ]);
    }

    public function submitQuery(): void
    {
        $this->validate([
            'question' => ['required', 'string'],
        ]);

        $result = app(RunQuery::class)->handle(auth()->user(), $this->question);

        $this->queryResult = $result;

        Notification::make()
            ->title('Query completed')
            ->success()
            ->send();
    }

    public function submitCommand(): void
    {
        $this->validate([
            'command' => ['required', 'string'],
            'targetType' => ['required', 'string'],
            'targetId' => ['required', 'string'],
            'reason' => ['required', 'string'],
        ]);

        try {
            $result = app(RunCommand::class)->handle(
                principal: auth()->user(),
                command: $this->command,
                target: ['type' => $this->targetType, $this->targetType . '_id' => $this->targetId],
                payload: $this->payload ?? [],
                reason: $this->reason,
                confirm: $this->confirm,
            );

            $this->commandResult = $result;

            Notification::make()
                ->title('Command executed')
                ->body('Recorded in audit trail.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Command rejected')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
