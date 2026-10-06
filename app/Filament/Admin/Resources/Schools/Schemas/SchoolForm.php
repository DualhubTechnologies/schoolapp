<?php

namespace App\Filament\Admin\Resources\Schools\Schemas;

use App\Filament\Pages\SchoolProfile;
use App\Models\School;
use App\Services\ParentMessages;
use App\Support\Edition;
use App\Support\EmailCheck;
use App\Support\ImageShrinker;
use App\Support\PrivateFiles;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * A school's profile: the platform owner's Edit school page and the
 * school's own School Profile page. Full-width sections, one below the
 * other, so no screen width leaves a blank gap beside a shorter column.
 */
class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSuperAdmin = fn (): bool => auth()->user()?->hasRole('Super Admin') ?? false;

        $columns = ['default' => 1, 'md' => 2, 'xl' => 4];

        return $schema
            ->components([

                Section::make('School profile')
                    ->icon('heroicon-o-building-library')
                    ->columnSpanFull()
                    ->columns($columns)
                    ->schema([

                        TextInput::make('name')
                            ->label('School name')
                            ->required()
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->disabled(fn () => ! $isSuperAdmin())
                            ->dehydrated(fn () => $isSuperAdmin()),

                        // Class levels, report cards and grading all branch on this.
                        Select::make('school_type')
                            ->label('School type')
                            ->options(School::TYPES)
                            ->required()
                            ->native(false)
                            ->disabled(fn () => ! $isSuperAdmin())
                            ->dehydrated(fn () => $isSuperAdmin()),

                        Select::make('boarding_type')
                            ->label('Day / boarding')
                            ->options(School::BOARDING_TYPES)
                            ->native(false),

                        Select::make('ownership')
                            ->options(School::OWNERSHIP)
                            ->native(false),

                        TextInput::make('expected_students')
                            ->label('Number of learners')
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('motto')
                            ->label('School motto')
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        TextInput::make('contact_person')
                            ->label('Contact person'),

                        TextInput::make('contact_title')
                            ->label('Their role'),

                        TextInput::make('phone')
                            ->label('Telephone number')
                            ->tel(),

                        EmailCheck::apply(TextInput::make('email'))
                            ->label('Email address')
                            ->email()
                            ->required(),

                        TextInput::make('address')
                            ->label('Address'),

                        TextInput::make('city')
                            ->label('City / District'),

                        Select::make('country')
                            ->label('Country')
                            ->options([
                                'Uganda' => 'Uganda',
                                'Kenya' => 'Kenya',
                                'Tanzania' => 'Tanzania',
                                'Rwanda' => 'Rwanda',
                                'South Sudan' => 'South Sudan',
                                'Burundi' => 'Burundi',
                                'Nigeria' => 'Nigeria',
                                'Ghana' => 'Ghana',
                            ])
                            ->searchable()
                            ->native(false),

                        TextInput::make('website')
                            ->label('Website')
                            ->url(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // The school sees its own code under Payments & settings.
                Section::make('SchoolHub account')
                    ->icon('heroicon-o-shield-check')
                    ->visible($isSuperAdmin)
                    ->columnSpanFull()
                    ->columns($columns)
                    ->schema([

                        Select::make('status')
                            ->label('Status')
                            ->options(School::STATUSES)
                            ->default('active')
                            ->required()
                            ->native(false),

                        TextInput::make('unique_code')
                            ->label('School code'),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required(),

                        TextEntry::make('registered')
                            ->label('Registered')
                            ->state(fn (?School $record): ?string => $record?->created_at?->format('j M Y, g:i a'))
                            ->placeholder('—')
                            ->visibleOn('edit'),

                        TextEntry::make('approved')
                            ->label('Approved')
                            ->state(fn (?School $record): ?string => $record?->approved_at
                                ? $record->approved_at->format('j M Y').($record->approved_by ? " by {$record->approved_by}" : '')
                                : null)
                            ->placeholder('Not yet')
                            ->visibleOn('edit'),

                        TextEntry::make('terms')
                            ->label('Terms accepted')
                            ->state(fn (?School $record): ?string => $record?->terms_accepted_at
                                ? $record->terms_accepted_at->format('j M Y')." (version {$record->terms_version})"
                                : null)
                            ->placeholder('—')
                            ->visibleOn('edit'),

                        TextEntry::make('rejection_reason')
                            ->label('Reason for rejecting')
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->visible(fn (?School $record): bool => filled($record?->rejection_reason)),
                    ]),

                Section::make('Payments & settings')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->columns($columns)
                    ->schema([

                        TextInput::make('fee_payment_bank')
                            ->label('Bank details')
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        TextInput::make('fee_payment_mobile_money')
                            ->label('Mobile money'),

                        Select::make('parent_sms_language')
                            ->label('Language of texts to parents')
                            ->options(ParentMessages::LANGUAGES)
                            ->default('en')
                            ->selectablePlaceholder(false),

                        Textarea::make('fee_payment_instructions')
                            ->label('Payment instructions for parents')
                            ->rows(2)
                            ->columnSpanFull(),

                        TextInput::make('unique_code')
                            ->label('School code')
                            ->disabled()
                            ->dehydrated(false)
                            ->hidden($isSuperAdmin),

                        TextInput::make('nssf_employer_number')
                            ->label('NSSF employer number'),

                        TextInput::make('tin_number')
                            ->label('TIN number'),

                        Select::make('timezone')
                            ->label('Timezone')
                            ->options([
                                'Africa/Kampala' => 'Africa/Kampala',
                                'Africa/Nairobi' => 'Africa/Nairobi',
                                'Africa/Kigali' => 'Africa/Kigali',
                                'Africa/Dar_es_Salaam' => 'Africa/Dar es Salaam',
                            ])
                            ->searchable()
                            ->native(false)
                            ->default('Africa/Kampala')
                            ->required(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'UGX' => 'UGX',
                                'KES' => 'KES',
                                'TZS' => 'TZS',
                                'RWF' => 'RWF',
                                'USD' => 'USD',
                            ])
                            ->native(false)
                            ->default('UGX')
                            ->required(),
                    ]),

                // On its own page (Settings -> SchoolPay) for schools; here
                // for the platform owner editing a school.
                self::schoolPaySection()
                    ->hidden(fn ($livewire): bool => Edition::isDesktop() || $livewire instanceof SchoolProfile),

                Section::make('Branding')
                    ->icon('heroicon-o-identification')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([

                        // Shrunk on the server, not in the browser: in-browser
                        // resizing hangs on iPhones for large pictures.
                        ImageShrinker::noBrowserResize(FileUpload::make('logo')
                            ->label('School logo')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['1:1'])
                            ->saveUploadedFileUsing(ImageShrinker::saveWithin(600, 600, square: true))
                            ->disk('public')
                            ->directory('school-logos')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)),

                        FileUpload::make('hm_signature')
                            ->label('Headteacher signature')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([null, '3:1', '4:1'])
                            ->saveUploadedFileUsing(ImageShrinker::saveWithin(900, 400))
                            ->disk(PrivateFiles::DISK)
                            ->directory('school-signatures')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240),
                    ]),
            ]);
    }

    /**
     * SchoolPay: parents pay to the learner's SchoolPay code and SchoolHub
     * records each payment itself (App\Services\SchoolPay). Used by the
     * school's own SchoolPay page and by the platform owner's school form.
     */
    public static function schoolPaySection(): Section
    {
        return Section::make('SchoolPay')
            ->description('If parents pay fees through SchoolPay, SchoolHub can record each payment for you. Ask SchoolPay (support@schoolpay.co.ug) for your API password.')
            ->icon('heroicon-o-credit-card')
            ->columnSpanFull()
            ->columns(['default' => 1, 'md' => 2])
            ->collapsible()
            ->hidden(fn (): bool => Edition::isDesktop())
            ->schema([
                Toggle::make('schoolpay_enabled')
                    ->label('Record SchoolPay payments automatically')
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('schoolpay_school_code')
                    ->label('SchoolPay school code')
                    ->helperText('Your school\'s number on SchoolPay.')
                    ->maxLength(20)
                    ->regex('/^\d+$/')
                    ->validationMessages(['regex' => 'Digits only, as SchoolPay gives it.'])
                    ->required(fn (Get $get): bool => (bool) $get('schoolpay_enabled')),

                TextInput::make('schoolpay_api_password')
                    ->label('SchoolPay API password')
                    ->password()
                    ->autocomplete('new-password')
                    ->maxLength(255)
                    ->placeholder(fn (?School $record): ?string => filled($record?->schoolpay_api_password) ? 'Saved. Type a new one only to change it' : null)
                    ->helperText('Not your SchoolPay login password: the API password SchoolPay gives the school.')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (Get $get, ?School $record): bool => (bool) $get('schoolpay_enabled') && blank($record?->schoolpay_api_password)),

                TextEntry::make('schoolpay_webhook')
                    ->label('Web hook address for the SchoolPay portal')
                    ->state(fn (?School $record): ?string => $record?->schoolPayWebhookUrl())
                    ->placeholder('Shown here once SchoolPay is turned on and saved.')
                    ->helperText('Paste this into "Web Hook URL" in your SchoolPay school portal and enable web hooks. Keep it private.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->columnSpanFull(),

                TextEntry::make('schoolpay_status')
                    ->label('Last check with SchoolPay')
                    ->state(function (?School $record): ?string {
                        if (! $record) {
                            return null;
                        }

                        if (filled($record->schoolpay_sync_error)) {
                            return 'Failed: '.$record->schoolpay_sync_error;
                        }

                        return $record->schoolpay_synced_at ? 'OK, '.$record->schoolpay_synced_at->diffForHumans() : null;
                    })
                    ->placeholder('Not yet. SchoolHub checks every morning and evening.')
                    ->color(fn (?School $record): ?string => filled($record?->schoolpay_sync_error) ? 'danger' : null)
                    ->columnSpanFull(),
            ]);
    }
}
