<?php

namespace App\Filament\Resources;

use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Domain\Issuance\Actions\ReturnItem;
use App\Domain\Issuance\Actions\ImportCatalogue;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Filament\Resources\IssuanceResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssuanceResource extends Resource
{
    protected static ?string $model = IssueEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('bursar');
    }

    public static function form(Form $form): Form
    {
        $catalogueItems = CatalogueItem::where('active', true)->pluck('name', 'id')->toArray();

        return $form->schema([
            Forms\Components\Section::make('Issuance Batch')
                ->schema([
                    Forms\Components\TextInput::make('student_id')
                        ->label('Student ID')
                        ->required()
                        ->helperText('Select or scan the student ID card.'),
                    Forms\Components\CheckboxList::make('items')
                        ->label('Catalogue Items')
                        ->options($catalogueItems)
                        ->required()
                        ->helperText('Check all items being issued to this student.'),
                    Forms\Components\Hidden::make('signature_ref')->default('pending'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_id')->label('Batch')->searchable(),
                Tables\Columns\TextColumn::make('student_id')->searchable(),
                Tables\Columns\TextColumn::make('catalogue_item_id'),
                Tables\Columns\TextColumn::make('cost')->money('XAF'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('issued_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('return')
                    ->label('Return Item')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (IssueEvent $record, array $data) {
                        app(ReturnItem::class)->handle(
                            issueEventId: $record->id,
                            reason: $data['reason'],
                            staffId: auth()->id(),
                        );
                    })
                    ->visible(fn (IssueEvent $record) => $record->status === 'issued'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssuances::route('/'),
            'create' => Pages\CreateIssuance::route('/create'),
        ];
    }
}
