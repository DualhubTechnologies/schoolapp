<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Support\MoneyInput;
use App\Models\BankAccount;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Services\ParentMessages;
use App\Services\SmsSender;
use App\Support\Modules;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The bursar's counter: find the student, see what they owe, take the
 * money, print the receipt. Designed to be done in under a minute.
 */
class ReceivePayment extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = 'Receive Payment';

    protected string $view = 'filament.pages.receive-payment';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The receipt just issued, shown until the next payment is started. */
    public ?int $issuedPaymentId = null;

    /**
     * A slip or transaction number already on another receipt, saved
     * anyway on the second click (one bank slip can pay for siblings).
     */
    public ?string $confirmedReference = null;

    public function mount(): void
    {
        $this->form->fill([
            'student_id' => request()->integer('student') ?: null,
            // Left empty on purpose: the bursar types what the parent
            // actually paid, or taps "Pay full balance".
            'amount' => null,
            'paid_on' => now()->toDateString(),
            'method' => 'cash',
            'bank_account_id' => $this->onlyBankAccountId(),
            'term_id' => Term::current()?->getKey(),
            'send_sms' => true,
        ]);
    }

    /** @return array<int, string> the school's bank accounts in use, id => "Fees (Stanbic · 9030…)" */
    protected function bankAccounts(): array
    {
        return BankAccount::where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (BankAccount $account): array => [$account->getKey() => $account->label()])
            ->all();
    }

    /** A school with one bank account has nothing to choose. */
    protected function onlyBankAccountId(): ?int
    {
        $accounts = $this->bankAccounts();

        return count($accounts) === 1 ? (int) array_key_first($accounts) : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Student')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Select::make('student_id')
                            ->hiddenLabel()
                            ->placeholder('Type a name or admission number…')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => $this->searchStudents($search))
                            ->getOptionLabelUsing(fn ($value) => $this->studentLabel(Student::with('schoolClass')->find($value)))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $this->issuedPaymentId = null;
                                $this->confirmedReference = null;
                                $student = $state ? Student::with('guardian')->whereKey($state)->first() : null;
                                $set('paid_by', $student?->guardian?->name);
                                $set('payer_phone', $student?->guardian?->phone);
                                // Typed by the bursar (or "Pay full balance"): a
                                // pre-filled balance gets saved by mistake.
                                $set('amount', null);
                            })
                            ->required(),
                    ]),

                Section::make('Payment')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Get $get) => filled($get('student_id')))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            MoneyInput::make('amount')
                                ->label('Amount received')
                                ->prefix('UGX')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->autofocus()
                                // The balance right under the box, so it is in view while typing (on a phone the card is above).
                                ->helperText(function (): string {
                                    $balance = (float) $this->getAccountProperty()['balance'];

                                    return match (true) {
                                        $balance > 0 => 'Owes UGX '.number_format($balance),
                                        $balance < 0 => 'In credit UGX '.number_format(abs($balance)),
                                        default => 'Fully paid',
                                    };
                                })
                                ->extraInputAttributes(['class' => 'text-lg font-semibold']),

                            DatePicker::make('paid_on')
                                ->label('Date paid')
                                ->native(false)
                                ->displayFormat('j M Y')
                                ->maxDate(now())
                                ->required(),
                        ]),

                        ToggleButtons::make('method')
                            ->label('Paid by')
                            ->options(StudentPayment::METHODS)
                            ->icons([
                                'cash' => 'heroicon-o-banknotes',
                                'mobile_money' => 'heroicon-o-device-phone-mobile',
                                'bank' => 'heroicon-o-building-library',
                                'schoolpay' => 'heroicon-o-credit-card',
                            ])
                            ->inline()
                            ->live()
                            ->required(),

                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('reference')
                                ->label(fn (Get $get) => match ($get('method')) {
                                    'mobile_money' => 'Mobile money transaction ID',
                                    'bank' => 'Bank slip / deposit number',
                                    'schoolpay' => 'SchoolPay reference',
                                    default => 'Reference (optional)',
                                })
                                ->required(fn (Get $get) => in_array($get('method'), StudentPayment::METHODS_NEEDING_REFERENCE, true))
                                ->maxLength(100),

                            Select::make('bank_account_id')
                                ->label('Paid into')
                                ->options(fn (): array => $this->bankAccounts())
                                ->placeholder('Which of the school\'s accounts?')
                                ->helperText('Printed on the receipt, and used to check it against that account\'s bank statement.')
                                ->native(false)
                                ->visible(fn (Get $get): bool => $get('method') === 'bank' && $this->bankAccounts() !== [])
                                ->required(fn (Get $get): bool => $get('method') === 'bank' && $this->bankAccounts() !== []),

                            Select::make('term_id')
                                ->label('For term')
                                ->options(fn () => Term::where('school_id', auth()->user()?->school_id)
                                    ->with('academicYear')
                                    ->get()
                                    ->sortByDesc(fn (Term $t) => $t->sortKey())
                                    ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                                    ->all())
                                ->native(false),

                            TextInput::make('paid_by')
                                ->label('Paid by (name)')
                                ->placeholder('Who brought the money')
                                ->maxLength(150),

                            TextInput::make('payer_phone')
                                ->label('Payer phone')
                                ->tel()
                                ->live(onBlur: true)
                                ->maxLength(30),
                        ]),

                        Toggle::make('send_sms')
                            ->label(fn (Get $get) => 'Text the receipt to '.($get('payer_phone') ?: 'the payer'))
                            ->helperText('An SMS with the amount, the new balance and a link to the learner\'s fees page.')
                            ->default(true)
                            ->visible(fn (Get $get) => SmsSender::normalisePhone($get('payer_phone')) !== null),

                        Textarea::make('notes')
                            ->rows(2)
                            ->placeholder('Optional'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $student = Student::where('school_id', auth()->user()->school_id)->whereKey($data['student_id'])->firstOrFail();

        // Guard against a double click saving the same payment twice.
        $duplicate = StudentPayment::where('student_id', $student->getKey())
            ->where('amount', $data['amount'])
            ->where('paid_on', $data['paid_on'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($duplicate) {
            Notification::make()->title('This payment was just recorded')->body('It looks like a double click, so it was not saved twice.')->warning()->send();

            return;
        }

        $reference = trim((string) ($data['reference'] ?? ''));

        if ($this->referenceAlreadyUsed($data['method'], $reference)) {
            return;
        }

        $bankAccountId = $data['method'] === 'bank' && filled($data['bank_account_id'] ?? null)
            ? BankAccount::where('school_id', $student->school_id)->whereKey($data['bank_account_id'])->value('id')
            : null;

        $payment = DB::transaction(fn () => StudentPayment::create([
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'term_id' => $data['term_id'] ?? null,
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'reference' => $reference !== '' ? $reference : null,
            'bank_account_id' => $bankAccountId,
            'paid_by' => $data['paid_by'] ?? null,
            'payer_phone' => $data['payer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]));

        $this->issuedPaymentId = $payment->getKey();
        $this->confirmedReference = null;

        $balance = $student->balance();
        $class = $student->schoolClass?->name;
        $body = 'UGX '.number_format((float) $payment->amount)." recorded for {$student->name}".($class ? " ({$class})" : '').'. '
            .($balance > 0 ? 'Balance now UGX '.number_format($balance).'.' : 'Fees fully paid.');

        if (($data['send_sms'] ?? false) && SmsSender::normalisePhone($data['payer_phone'] ?? null)) {
            $failed = app(ParentMessages::class)->sendReceipt($payment->load('student.school', 'student.schoolClass'), $data['payer_phone']);
            $body .= $failed ? " The SMS was not sent: {$failed}" : " Receipt texted to {$data['payer_phone']}.";
        }

        Notification::make()
            ->title("Receipt {$payment->receipt_no} issued")
            ->body($body)
            ->success()
            ->send();

        // Keep the student selected (a parent often pays for siblings one
        // after another, but the next one starts with a clean form).
        $this->form->fill([
            'student_id' => $student->getKey(),
            'amount' => null,
            'paid_on' => now()->toDateString(),
            'method' => $data['method'],
            'bank_account_id' => $data['bank_account_id'] ?? $this->onlyBankAccountId(),
            'term_id' => $data['term_id'] ?? null,
            'paid_by' => $data['paid_by'] ?? null,
            'payer_phone' => $data['payer_phone'] ?? null,
            'send_sms' => $data['send_sms'] ?? true,
        ]);
    }

    /**
     * The same bank slip or mobile money transaction already on a receipt:
     * stop and say which, the first time. One slip can rightly pay for
     * siblings, so saving again with the same number goes ahead.
     */
    protected function referenceAlreadyUsed(string $method, string $reference): bool
    {
        if (! in_array($method, ['bank', 'mobile_money'], true) || $reference === '' || $this->confirmedReference === mb_strtolower($reference)) {
            return false;
        }

        $earlier = StudentPayment::with('student')
            ->where('school_id', auth()->user()?->school_id)
            ->where('method', $method)
            ->whereRaw('LOWER(TRIM(reference)) = ?', [mb_strtolower($reference)])
            ->orderBy('id')
            ->first();

        if (! $earlier) {
            return false;
        }

        $this->confirmedReference = mb_strtolower($reference);
        $what = $method === 'bank' ? 'Bank slip' : 'Transaction';

        Notification::make()
            ->title("{$what} {$reference} is already on a receipt")
            ->body("Receipt {$earlier->receipt_no}, UGX ".number_format((float) $earlier->amount).' for '.($earlier->student->name ?? 'a learner').' on '.Carbon::parse($earlier->paid_on)->format('j M Y').'. Check the number. If this one payment really covers more than one learner, e.g. brothers and sisters, click Save again.')
            ->warning()
            ->persistent()
            ->send();

        return true;
    }

    public function startNew(): void
    {
        $this->issuedPaymentId = null;
        $this->mount();
    }

    public function fillBalance(): void
    {
        $balance = $this->getSelectedStudentProperty()?->balance() ?? 0;

        if ($balance > 0) {
            $this->data['amount'] = (int) round($balance);
        }
    }

    // ── What the view needs ──

    public function getSelectedStudentProperty(): ?Student
    {
        $id = $this->data['student_id'] ?? null;

        return $id
            ? Student::with(['schoolClass', 'section', 'guardian', 'residencyType'])
                ->where('school_id', auth()->user()?->school_id)
                ->find($id)
            : null;
    }

    public function getIssuedPaymentProperty(): ?StudentPayment
    {
        return $this->issuedPaymentId ? StudentPayment::with('student')->find($this->issuedPaymentId) : null;
    }

    /**
     * @return array{balance: float, term_billed: float, term_paid: float, recent: Collection}
     */
    public function getAccountProperty(): array
    {
        $student = $this->getSelectedStudentProperty();
        $term = Term::current();

        if (! $student) {
            return ['balance' => 0, 'term_billed' => 0, 'term_paid' => 0, 'recent' => collect()];
        }

        return [
            'balance' => $student->balance(),
            'term_billed' => $term ? $student->chargedInTerm($term) : 0,
            'term_paid' => $term ? (float) $student->payments()->where('term_id', $term->getKey())->sum('amount') : 0,
            'recent' => $student->payments()->latest('paid_on')->latest('id')->limit(4)->get(),
        ];
    }

    /**
     * WhatsApp, ready to send the receipt text to the payer.
     */
    public function whatsAppUrl(StudentPayment $payment): string
    {
        return app(ParentMessages::class)->whatsAppReceiptUrl(
            $payment->loadMissing('student.school', 'student.schoolClass', 'student.guardian'),
            $payment->payer_phone ?: $payment->student?->guardian?->phone,
        );
    }

    public static function receiptUrl(StudentPayment|int $payment, bool $print = false): string
    {
        return route('filament.app.fees.receipt', ['payment' => $payment instanceof StudentPayment ? $payment->getKey() : $payment])
            .($print ? '?print=1' : '');
    }

    protected function searchStudents(string $search): array
    {
        return Student::query()
            ->with('schoolClass')
            ->where('school_id', auth()->user()?->school_id)
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('admission_no', 'like', "%{$search}%"))
            ->orderByRaw('admission_no = ? desc', [$search])
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Student $s) => [$s->id => $this->studentLabel($s)])
            ->all();
    }

    protected function studentLabel(?Student $student): ?string
    {
        if (! $student) {
            return null;
        }

        return trim(($student->name ?: 'No name')." — {$student->admission_no}".($student->schoolClass ? " ({$student->schoolClass->name})" : ''));
    }

    public static function canAccess(): bool
    {
        return Modules::allows('fees');
    }
}
