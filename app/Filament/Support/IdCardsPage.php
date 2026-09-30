<?php

namespace App\Filament\Support;

use App\Models\IdCardTemplate;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Modules;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * What the Student and Staff ID Cards pages share: the school's Template
 * (orientation, colours, validity, the rules on the back), the preview of
 * every front beside the one shared back, and Print / Export PDF -- which
 * stay switched off until every card in the batch has what it needs, so a
 * school never finds out a card is incomplete only after printing it.
 *
 * A page lists its holders one of three ways: one person (?id=, from a
 * table row), a chosen set (?ids=, from a bulk action), or its own filters.
 *
 * @property Collection<int, Student>|Collection<int, Staff> $holders
 * @property Collection<int, array<string, mixed>> $cards
 * @property IdCardTemplate $cardTemplate
 */
abstract class IdCardsPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'ID Cards';

    protected string $view = 'filament.pages.id-cards';

    /** Set when opened for one person (a table row action). */
    public ?int $holderId = null;

    /** Set when opened for a chosen set (a table bulk action), comma-separated. */
    public string $holderIds = '';

    /** What is typed in the search box; applied by the Search button or Enter. */
    public string $searchInput = '';

    /** The search in force: name or number. */
    public string $search = '';

    /** '' everyone, 'ready' only cards that can print, 'missing' only those missing details. */
    public string $readiness = '';

    public const READINESS = [
        '' => 'All cards',
        'ready' => 'Ready to print',
        'missing' => 'Missing details',
    ];

    /** 'students' or 'staff': which print/export route and which records. */
    abstract public function holderType(): string;

    /**
     * The people the page's own filters pick out.
     *
     * @return Collection<int, Student>|Collection<int, Staff>
     */
    abstract protected function filteredHolders(): Collection;

    /**
     * The given people, scoped to the school.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Student>|Collection<int, Staff>
     */
    abstract protected function holdersById(array $ids): Collection;

    /** Where to fill in what a card is missing. */
    abstract public function editUrl(Student|Staff $holder): string;

    /** The Blade partial with this page's filters. */
    abstract public function filtersView(): string;

    /** What the page says before anyone is chosen. */
    abstract public function emptyHint(): string;

    public static function canAccess(): bool
    {
        return Modules::allows('id_cards');
    }

    public function mount(): void
    {
        $this->holderId = request()->integer('id') ?: null;
        $this->holderIds = (string) request()->query('ids', '');
    }

    /** Forget the chosen person or set and go back to the page's filters. */
    public function clearSelection(): void
    {
        $this->holderId = null;
        $this->holderIds = '';
        $this->refreshCards();
    }

    protected function refreshCards(): void
    {
        unset($this->holders, $this->cards);
    }

    /** The Search button (or Enter in the search box). */
    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->refreshCards();
    }

    public function clearSearch(): void
    {
        $this->searchInput = '';
        $this->search = '';
        $this->refreshCards();
    }

    public function updatedReadiness(): void
    {
        $this->refreshCards();
    }

    /**
     * A query narrowed to what the search box holds: any of the columns
     * contains it (case does not matter).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    protected function applySearchTo(Builder $query, array $columns): Builder
    {
        if ($this->search === '') {
            return $query;
        }

        // LIKE ignores case on MySQL's default collation and for plain
        // letters on SQLite, so "nakato" finds "Nakato".
        $term = '%'.$this->search.'%';

        return $query->where(function ($q) use ($columns, $term): void {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $term);
            }
        });
    }

    public function hasExplicitSelection(): bool
    {
        return $this->holderId !== null || $this->holderIds !== '';
    }

    /** @return array<int, int> */
    protected function explicitIds(): array
    {
        if ($this->holderId) {
            return [$this->holderId];
        }

        return collect(explode(',', $this->holderIds))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, Student>|Collection<int, Staff> */
    #[Computed]
    public function holders(): Collection
    {
        $ids = $this->explicitIds();

        if ($ids !== []) {
            return $this->holdersById($ids);
        }

        $holders = $this->filteredHolders();

        if ($this->readiness === '') {
            return $holders;
        }

        $service = app(IdCardService::class);

        return $holders->filter(fn (Student|Staff $holder): bool => $service->isReady($holder) === ($this->readiness === 'ready'))->values();
    }

    /**
     * Each person's front, with what it is missing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function cards(): Collection
    {
        return app(IdCardService::class)->cardsFor($this->holders, $this->cardTemplate);
    }

    public function notReadyCount(): int
    {
        return $this->cards->where('ready', false)->count();
    }

    public function canPrint(): bool
    {
        return $this->cards->isNotEmpty() && $this->notReadyCount() === 0;
    }

    public function printUrl(): string
    {
        return route('filament.app.id-cards.print', ['type' => $this->holderType(), 'ids' => $this->idsParam(), 'print' => 1]);
    }

    public function exportUrl(): string
    {
        return route('filament.app.id-cards.export', ['type' => $this->holderType(), 'ids' => $this->idsParam()]);
    }

    protected function idsParam(): string
    {
        return $this->holders->pluck('id')->implode(',');
    }

    // ── Template ──

    /** Only those who manage the school's settings change its template. */
    public function canEditTemplate(): bool
    {
        return Modules::allows('settings');
    }

    /** The school's saved template, or the defaults until it saves one. */
    #[Computed]
    public function cardTemplate(): IdCardTemplate
    {
        return IdCardTemplate::forSchool((int) auth()->user()?->school_id);
    }

    /**
     * What every card shares: school details for the header and the back.
     *
     * @return array<string, mixed>|null
     */
    public function shared(): ?array
    {
        $school = auth()->user()?->school;

        return $school ? app(IdCardService::class)->schoolData($school, $this->cardTemplate) : null;
    }

    /**
     * The sample card in the Template window, drawn from the choices as
     * they stand in the form (not yet saved).
     *
     * @return array<string, mixed>
     */
    protected function templatePreview(Get $get): array
    {
        $draft = new IdCardTemplate([
            'orientation' => $get('orientation') ?: IdCardTemplate::DEFAULTS['orientation'],
            'primary_color' => IdCardTemplate::hex($get('primary_color'), IdCardTemplate::DEFAULTS['primary_color']),
            'accent_color' => IdCardTemplate::hex($get('accent_color'), IdCardTemplate::DEFAULTS['accent_color']),
            'validity' => $get('validity') ?: IdCardTemplate::DEFAULTS['validity'],
            'validity_months' => (int) $get('validity_months') ?: 12,
            'back_notes' => $get('back_notes'),
        ]);

        return app(IdCardService::class)->sample($draft, $this->holderType() === 'staff');
    }

    /** @return array<string, string> */
    public function design(): array
    {
        return app(IdCardService::class)->design($this->cardTemplate);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label('Template')
                ->icon('heroicon-o-swatch')
                ->color('gray')
                ->visible(fn (): bool => $this->canEditTemplate())
                ->modalHeading('ID card template')
                ->modalDescription('How your school\'s student and staff ID cards look. Saved for the school: every preview, print and export uses it until you change it.')
                ->modalSubmitActionLabel('Save template')
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    ...IdCardTemplate::DEFAULTS,
                    ...$this->cardTemplate->only(['orientation', 'primary_color', 'accent_color', 'validity', 'validity_months', 'back_notes']),
                    'back_notes' => $this->cardTemplate->back_notes ?? IdCardTemplate::DEFAULT_BACK_NOTES,
                ])
                ->schema([
                    View::make('filament.pages.id-cards.template-preview')
                        ->viewData(fn (Get $get): array => ['sample' => $this->templatePreview($get)]),
                    ToggleButtons::make('orientation')
                        ->options(IdCardTemplate::ORIENTATIONS)
                        ->icons(['landscape' => 'heroicon-o-rectangle-group', 'portrait' => 'heroicon-o-device-phone-mobile'])
                        ->inline()
                        ->live()
                        ->required(),
                    Grid::make(2)->schema([
                        ColorPicker::make('primary_color')
                            ->label('Main colour')
                            ->helperText('Header, footer and name.')
                            ->live(debounce: 300)
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->required(),
                        ColorPicker::make('accent_color')
                            ->label('Accent colour')
                            ->helperText('Photo frame and trim.')
                            ->live(debounce: 300)
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->required(),
                    ]),
                    Grid::make(2)->schema([
                        Select::make('validity')
                            ->label('Card valid')
                            ->options(IdCardTemplate::VALIDITY)
                            ->native(false)
                            ->live()
                            ->required(),
                        TextInput::make('validity_months')
                            ->label('Months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->live(debounce: 500)
                            ->visible(fn (Get $get): bool => $get('validity') === 'months')
                            ->required(fn (Get $get): bool => $get('validity') === 'months'),
                    ]),
                    Textarea::make('back_notes')
                        ->label('Rules on the back')
                        ->helperText('One rule per line; up to five are printed. The back is the same on every card.')
                        ->rows(4)
                        ->live(debounce: 500)
                        ->maxLength(600),
                ])
                ->action(function (array $data): void {
                    $schoolId = auth()->user()?->school_id;

                    if (! $schoolId || ! $this->canEditTemplate()) {
                        return;
                    }

                    IdCardTemplate::updateOrCreate(['school_id' => $schoolId], [
                        'orientation' => $data['orientation'],
                        'primary_color' => strtolower($data['primary_color']),
                        'accent_color' => strtolower($data['accent_color']),
                        'validity' => $data['validity'],
                        'validity_months' => (int) ($data['validity_months'] ?? 12) ?: 12,
                        'back_notes' => trim((string) ($data['back_notes'] ?? '')) ?: null,
                    ]);

                    unset($this->cardTemplate);
                    $this->refreshCards();

                    Notification::make()->title('Template saved')->body('Your school\'s ID cards will use it from now on.')->success()->send();
                }),
        ];
    }
}
