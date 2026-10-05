<?php

namespace App\Filament\Pages;

use App\Filament\App\Resources\Billing\BillingResource;
use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Models\FeeStructure;
use App\Models\Term;
use App\Services\AttentionItems;
use App\Services\BillingService;
use App\Support\Modules;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The start of a new term: the termly fees in force, kept as they were or
 * updated for this term, then every learner billed in one go. A changed
 * amount is a new version of the fee from this term on, so earlier terms
 * keep what they were billed (the Fee structure sheet shows any term).
 * The bursar is sent here by the dashboard and the bell until the current
 * term is billed (BillingService::termNeedsBilling).
 */
class StartTermBilling extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'fees/start-term';

    protected static ?string $title = 'Start the term: fees and billing';

    protected string $view = 'filament.pages.start-term-billing';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return filled(auth()->user()?->school_id) && (Modules::allows('fees') || Modules::allows('finance'));
    }

    public function term(): ?Term
    {
        return Term::current();
    }

    public function previousTerm(): ?Term
    {
        return $this->term()?->previous();
    }

    public function mount(): void
    {
        $term = $this->term();

        $this->form->fill([
            'mode' => 'keep',
            'bill_now' => true,
            'fees' => $term
                ? FeeStructure::termlyFor($term)
                    ->load(['schoolClass', 'residencyType', 'term.academicYear'])
                    ->sortBy(fn (FeeStructure $fee): string => ($fee->schoolClass->name ?? '').'|'.$fee->name)
                    ->map(fn (FeeStructure $fee): array => [
                        'fee_id' => $fee->getKey(),
                        'label' => collect([
                            $fee->schoolClass->name ?? 'All classes',
                            $fee->name,
                            $fee->residencyType?->name,
                        ])->filter()->implode(' · '),
                        'since' => $fee->term ? 'Since '.$fee->term->label() : 'Since before terms were set',
                        'amount' => (float) $fee->amount,
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Radio::make('mode')
                    ->label('Fees for this term')
                    ->options([
                        'keep' => 'Keep the same fees as last term',
                        'update' => 'Update some amounts for this term',
                    ])
                    ->descriptions([
                        'keep' => 'Every fee below is billed at the amount shown.',
                        'update' => 'Type the new amounts. Earlier terms keep their old amounts on record.',
                    ])
                    ->live()
                    ->required(),

                Repeater::make('fees')
                    ->label('Termly fees in force')
                    ->schema([
                        Hidden::make('fee_id'),
                        TextInput::make('label')
                            ->label('Fee')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(fn (Get $get): ?string => $get('since')),
                        Hidden::make('since')->dehydrated(false),
                        TextInput::make('amount')
                            ->label('Amount')
                            ->prefix('UGX')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('../../mode') !== 'update'),
                    ])
                    ->columns(2)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->visible(fn (Get $get): bool => filled($get('fees'))),

                Toggle::make('bill_now')
                    ->label('Bill every learner now')
                    ->helperText('Adds this term\'s fees, transport and discounts to each learner\'s account. Running it again never charges twice.'),
            ]);
    }

    public function save(BillingService $billing): void
    {
        $term = $this->term();

        if (! $term) {
            Notification::make()->title('Set the current term first')->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $changed = 0;

        if (($data['mode'] ?? 'keep') === 'update') {
            $amounts = collect($data['fees'] ?? [])
                ->filter(fn ($row): bool => is_array($row) && isset($row['fee_id'], $row['amount']))
                ->mapWithKeys(fn ($row): array => [(int) $row['fee_id'] => $row['amount']])
                ->all();

            $changed = $billing->setTermFees($term, $amounts);
        }

        $body = $changed > 0
            ? "{$changed} ".str('fee')->plural($changed)." updated for {$term->label()}; earlier terms keep their old amounts."
            : 'Fees kept as they were.';

        if ($data['bill_now'] ?? false) {
            $result = $billing->billTerm($term);
            $body .= " {$result['charges_added']} ".str('charge')->plural($result['charges_added'])." added for {$result['students_changed']} ".str('learner')->plural($result['students_changed']).'.';
        }

        if ($user = auth()->user()) {
            AttentionItems::forget($user);
        }

        Notification::make()->title('Term set up')->body($body)->success()->send();

        $this->redirect(($data['bill_now'] ?? false) ? BillingResource::getUrl() : FeeStructureResource::getUrl());
    }
}
