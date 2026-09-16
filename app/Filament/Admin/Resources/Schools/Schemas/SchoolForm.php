<?php

namespace App\Filament\Admin\Resources\Schools\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSuperAdmin = fn (): bool => auth()->user()?->hasRole('Super Admin') ?? false;

        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        // LEFT BIG CARD
                        Section::make('School Information')
                            ->description('Manage the school’s main profile information.')
                            ->columnSpan([
                                'default' => 3,
                                'lg' => 2,
                            ])
                            ->columns(2)
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
                                    ->required()
                                    ->disabled(fn () => ! $isSuperAdmin())
                                    ->dehydrated(fn () => $isSuperAdmin()),

                                Select::make('status')
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

                                Textarea::make('description')
                                    ->label('Description')
                                    ->placeholder('e.g., Mixed Day & Boarding School — Secondary, Primary, or Nursery')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                TextInput::make('motto')
                                    ->label('School Motto')
                                    ->columnSpanFull(),

                                TextInput::make('address')
                                    ->placeholder('e.g., P.O BOX 001 KLA'),

                                TextInput::make('city'),

                                Select::make('country')
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
                                    ->url()
                                    ->placeholder('https://...'),

                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required(),

                                TextInput::make('phone')
                                    ->label('Telephone Number')
                                    ->tel(),
                            ]),

                        // RIGHT COLUMN
                        Grid::make(1)
                            ->columnSpan([
                                'default' => 3,
                                'lg' => 1,
                            ])
                            ->schema([
                                Section::make('School Summary')
                                    ->description('Quick school overview.')
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label('School Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions([null, '1:1'])
                                            ->automaticallyResizeImagesToWidth('400')
                                            ->automaticallyResizeImagesToHeight('400')
                                            ->disk('public')
                                            ->directory('school-logos')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->helperText('Upload the official school logo.'),

                                        Placeholder::make('summary_status')
                                            ->label('Status')
                                            ->content(fn ($get) => ucfirst($get('status') ?? 'N/A')),

                                        Placeholder::make('summary_code')
                                            ->label('Unique Code')
                                            ->content(fn ($get) => $get('unique_code') ?: '—'),

                                        Placeholder::make('summary_slug')
                                            ->label('Slug')
                                            ->content(fn ($get) => $get('slug') ?: '—'),
                                    ]),

                                Section::make('Branding & Settings')
                                    ->description('Secondary settings and official branding.')
                                    ->columns(1)
                                    ->schema([
                                        FileUpload::make('hm_signature')
                                            ->label('Upload H/M Signature')
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatioOptions([null, '3:1', '4:1'])
                                            ->automaticallyResizeImagesToWidth('400')
                                            ->automaticallyResizeImagesToHeight('400')
                                            ->disk('public')
                                            ->directory('school-signatures')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->helperText('Upload the official school signature.'),

                                        TextInput::make('nssf_employer_number')
                                            ->label('NSSF employer number')
                                            ->placeholder('e.g. ER/12345'),

                                        TextInput::make('tin_number')
                                            ->label('TIN number')
                                            ->placeholder('e.g. 1001234567'),

                                        TextInput::make('timezone')
                                            ->required()
                                            ->default('Africa/Kampala'),

                                        TextInput::make('currency')
                                            ->required()
                                            ->default('UGX'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}