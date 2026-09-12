<?php

namespace App\Filament\App\Resources\Staff\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'bankDetails';

    protected static ?string $title = 'Payment Details';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payment_method')
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'mobile_money' => 'Mobile Money',
                        'cash' => 'Cash',
                    ])
                    ->default('bank_transfer')
                    ->required()
                    ->live(),
                TextInput::make('bank_name')
                    ->required()
                    ->visible(fn (Get $get) => $get('payment_method') === 'bank_transfer'),
                TextInput::make('branch')
                    ->visible(fn (Get $get) => $get('payment_method') === 'bank_transfer'),
                TextInput::make('account_name')
                    ->required()
                    ->visible(fn (Get $get) => $get('payment_method') === 'bank_transfer'),
                TextInput::make('account_number')
                    ->required()
                    ->visible(fn (Get $get) => $get('payment_method') === 'bank_transfer'),
                Select::make('mobile_money_provider')
                    ->options([
                        'MTN' => 'MTN Mobile Money',
                        'Airtel' => 'Airtel Money',
                    ])
                    ->visible(fn (Get $get) => $get('payment_method') === 'mobile_money')
                    ->required(fn (Get $get) => $get('payment_method') === 'mobile_money'),
                TextInput::make('mobile_money_number')
                    ->tel()
                    ->placeholder('+256...')
                    ->visible(fn (Get $get) => $get('payment_method') === 'mobile_money')
                    ->required(fn (Get $get) => $get('payment_method') === 'mobile_money'),
                Toggle::make('is_primary')
                    ->default(true)
                    ->helperText('Primary payment method is used for salary payments.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('account_number')
            ->columns([
                TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank_transfer' => 'info',
                        'mobile_money' => 'warning',
                        'cash' => 'gray',
                    }),
                TextColumn::make('bank_name')
                    ->placeholder('—'),
                TextColumn::make('account_number')
                    ->placeholder('—'),
                TextColumn::make('mobile_money_provider')
                    ->label('Provider')
                    ->placeholder('—'),
                TextColumn::make('mobile_money_number')
                    ->label('Mobile number')
                    ->placeholder('—'),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add payment method'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}