<?php

namespace App\Filament\Admin\Resources\Schools\Schemas;

use App\Models\School;
use App\Services\ParentMessages;
use App\Support\EmailCheck;
use App\Support\ImageShrinker;
use App\Support\PrivateFiles;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSuperAdmin = fn (): bool => auth()->user()?->hasRole('Super Admin') ?? false;

        return $schema
            ->components([

                Grid::make([
                    'default' => 1,
                    'lg' => 4,
                ])
                    // The form itself has two columns; this layout needs all of it.
                    ->columnSpanFull()
                    ->schema([

                        /*
                        |--------------------------------------------------------------------------
                        | LEFT - SCHOOL INFORMATION
                        |--------------------------------------------------------------------------
                        */

                        Section::make('School Information')
                            ->description('Manage the school’s main profile information.')
                            ->icon('heroicon-o-building-library')
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 3,
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'lg' => 3,
                            ])
                            ->schema([

                                TextInput::make('name')
                                    ->label('School Name')
                                    ->required()
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                TextInput::make('unique_code')
                                    ->label('Unique Code')
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                // Identity, not configuration: class levels,
                                // report cards and grading all branch on this.
                                Select::make('school_type')
                                    ->label('School type')
                                    ->options(School::TYPES)
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin())
                                    ->helperText('Decides what the school sees everywhere: Primary shows nursery and primary only; Secondary shows O-Level and A-Level only. Set once at signup — changing it later does not convert existing classes.'),

                                Select::make('boarding_type')
                                    ->label('Day / boarding')
                                    ->options(School::BOARDING_TYPES)
                                    ->native(false),

                                Select::make('ownership')
                                    ->options(School::OWNERSHIP)
                                    ->native(false),

                                TextInput::make('expected_students')
                                    ->label('Number of learners (approx.)')
                                    ->numeric()
                                    ->minValue(1),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(School::STATUSES)
                                    ->default('active')
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                TextInput::make('motto')
                                    ->label('School Motto')
                                    ->placeholder('e.g. Ora et Labora'),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->placeholder('e.g., Mixed Day & Boarding School — Secondary, Primary, or Nursery')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                TextInput::make('address')
                                    ->label('Address')
                                    ->placeholder('e.g. NYAMITANGA, MBARARA'),

                                TextInput::make('city')
                                    ->label('City / District')
                                    ->placeholder('e.g. MBARARA'),

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

                                EmailCheck::apply(TextInput::make('email'))
                                    ->label('Email address')
                                    ->email()
                                    ->required(),

                                TextInput::make('phone')
                                    ->label('Telephone Number')
                                    ->tel(),

                                TextInput::make('website')
                                    ->label('Website')
                                    ->url()
                                    ->placeholder('https://...'),
                            ]),

                        Section::make('Fee Payment Details')
                            ->description('Shown to parents on receipts, statements and the admission letter, so they know how to pay.')
                            ->icon('heroicon-o-banknotes')
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 3,
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextInput::make('fee_payment_bank')
                                    ->label('Bank details')
                                    ->placeholder('e.g. Stanbic Bank, A/C 9030012345678, Green Hill School Ltd'),

                                TextInput::make('fee_payment_mobile_money')
                                    ->label('Mobile money')
                                    ->placeholder('e.g. MTN 0772 000000 (Green Hill School)'),

                                Textarea::make('fee_payment_instructions')
                                    ->label('Other instructions')
                                    ->placeholder('e.g. Use the student\'s admission number as the payment reference, then bring the slip to the bursar.')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Select::make('parent_sms_language')
                                    ->label('Language of texts to parents')
                                    ->options(ParentMessages::LANGUAGES)
                                    ->default('en')
                                    ->selectablePlaceholder(false)
                                    ->helperText('Receipts and fee reminders sent by SMS or WhatsApp.'),
                            ]),

                        /*
                        |--------------------------------------------------------------------------
                        | RIGHT - BRANDING & SETTINGS
                        |--------------------------------------------------------------------------
                        */

                        Grid::make(1)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->schema([

                                Section::make('Branding')
                                    ->description('Logo and headteacher signature.')
                                    ->icon('heroicon-o-identification')
                                    ->schema([

                                        ImageShrinker::noBrowserResize(FileUpload::make('logo')
                                            ->label('School Logo')
                                            ->image()
                                            ->avatar()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions(['1:1'])
                                            // Shrunk on the server, not in the browser: in-browser
                                            // resizing hangs on iPhones for large pictures.
                                            ->saveUploadedFileUsing(ImageShrinker::saveWithin(600, 600, square: true))
                                            ->disk('public')
                                            ->directory('school-logos')
                                            ->visibility('public')
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ])
                                            ->maxSize(10240)
                                            ->helperText('Square crop. Appears on reports and documents.')),

                                        FileUpload::make('hm_signature')
                                            ->label('Headteacher Signature')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions([null, '3:1', '4:1'])
                                            ->saveUploadedFileUsing(ImageShrinker::saveWithin(900, 400))
                                            ->disk(PrivateFiles::DISK)
                                            ->directory('school-signatures')
                                            ->visibility('private')
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ])
                                            ->maxSize(10240)
                                            ->helperText('Crop freely — a signature is wider than it is tall.'),
                                    ]),

                                Section::make('Statutory & Settings')
                                    ->description('Tax registration and regional defaults.')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'lg' => 1,
                                    ])
                                    ->schema([

                                        TextInput::make('nssf_employer_number')
                                            ->label('NSSF employer number')
                                            ->placeholder('e.g. ER/12345'),

                                        TextInput::make('tin_number')
                                            ->label('TIN number')
                                            ->placeholder('e.g. 1001234567'),

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
                            ]),
                    ]),
            ]);
    }
}
