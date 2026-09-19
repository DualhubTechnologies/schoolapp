<?php

namespace App\Filament\Admin\Resources\Schools\Schemas;

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
        $isSuperAdmin = fn (): bool =>
            auth()->user()?->hasRole('Super Admin') ?? false;

        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'xl' => 12,
                ])
                    ->schema([
                        Section::make('School Information')
                            ->description('Manage the school’s main profile information.')
                            ->icon('heroicon-o-building-library')
                            ->columnSpan([
                                'default' => 1,
                                'xl' => 8,
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
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

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'suspended' => 'Suspended',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                TextInput::make('motto')
                                    ->label('School Motto')
                                    ->placeholder('e.g. Ora et Labora')
                                    ->columnSpanFull(),

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

                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required(),

                                TextInput::make('phone')
                                    ->label('Telephone Number')
                                    ->tel(),

                                TextInput::make('website')
                                    ->label('Website')
                                    ->url()
                                    ->placeholder('https://...')
                                    ->columnSpanFull(),
                            ]),

                        Grid::make(1)
                            ->columnSpan([
                                'default' => 1,
                                'xl' => 4,
                            ])
                            ->schema([
                                Section::make('Branding')
                                    ->description('Logo and headteacher signature.')
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label('School Logo')
                                            ->image()
                                            ->avatar()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions(['1:1'])
                                            ->automaticallyResizeImagesToWidth('400')
                                            ->automaticallyResizeImagesToHeight('400')
                                            ->disk('public')
                                            ->directory('school-logos')
                                            ->visibility('public')
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ])
                                            ->maxSize(2048)
                                            ->helperText('Square crop. Appears on reports and documents.'),

                                        FileUpload::make('hm_signature')
                                            ->label('Headteacher Signature')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions([null, '3:1', '4:1'])
                                            ->automaticallyResizeImagesToWidth('600')
                                            ->disk('public')
                                            ->directory('school-signatures')
                                            ->visibility('public')
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ])
                                            ->maxSize(2048)
                                            ->helperText('Crop freely — a signature is wider than it is tall.'),
                                    ]),

                                Section::make('Statutory & Settings')
                                    ->description('Tax registration and regional defaults.')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 1,
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
