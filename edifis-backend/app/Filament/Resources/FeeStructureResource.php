<?php

namespace App\Filament\Resources;

use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Fees\Models\FeeStructure;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Students\Models\Student;
use App\Filament\Resources\FeeStructureResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Ramsey\Uuid\Uuid;

class FeeStructureResource extends Resource
{
    protected static ?string $model = FeeStructure::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Fees';
    protected static ?string $modelLabel = 'Fee structure';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['bursar', 'principal', 'school_admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('class_id')->label('Class')->required()
                ->options(fn () => SchoolClass::pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('name')->label('Fee name')->required()->maxLength(255)
                ->helperText('e.g. Tuition, PTA, Boarding'),
            Forms\Components\TextInput::make('amount')->label('Amount (XAF)')->required()->numeric()->minValue(0),
            Forms\Components\Select::make('applies_to')->required()->default('all')
                ->options(FeeStructure::APPLIES),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class_id')->label('Class')
                    ->formatStateUsing(fn ($state) => optional(SchoolClass::find($state))->name ?? $state),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('applies_to')->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('bill')
                    ->label('Bill this class')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalDescription('Charge every active student in this class their applicable fees. Safe to re-run — it never double-charges.')
                    ->action(fn (FeeStructure $record) => static::billClass($record->class_id)),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    /** Bill all active students in a class for the fees that apply to them (idempotent). */
    public static function billClass(string $classId): void
    {
        $fees = FeeStructure::where('class_id', $classId)->get();
        $students = Student::where('active', true)->where('current_class_id', $classId)->get();

        $count = 0;
        $total = 0;
        foreach ($students as $student) {
            $status = $student->boarding_status ?? 'day';
            foreach ($fees as $fee) {
                if ($fee->applies_to !== 'all' && $fee->applies_to !== $status) {
                    continue;
                }
                $sourceEventId = (string) Uuid::uuid5(Uuid::NAMESPACE_DNS, "fee:{$fee->id}:{$student->id}");
                if (LedgerEntry::where('source_event_id', $sourceEventId)->exists()) {
                    continue;
                }
                LedgerEntry::create([
                    'id' => (string) Uuid::uuid7(),
                    'student_id' => $student->id,
                    'source_event_id' => $sourceEventId,
                    'amount' => (int) $fee->amount,
                    'description' => $fee->name,
                    'posted_at' => now(),
                ]);
                $count++;
                $total += (int) $fee->amount;
            }
        }

        Notification::make()
            ->title("Billed {$count} charges ({$total} XAF)")
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeeStructures::route('/'),
            'create' => Pages\CreateFeeStructure::route('/create'),
            'edit' => Pages\EditFeeStructure::route('/{record}/edit'),
        ];
    }
}
