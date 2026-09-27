<?php

namespace App\Filament\App\Resources\Staff\RelationManagers;

use App\Models\AllowanceType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllowancesRelationManager extends RelationManager
{
    protected static string $relationship = 'allowances';

    protected static ?string $title = 'Allowances';

    protected static ?string $modelLabel = 'allowance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('allowance_type_id')
                    ->label('Allowance type')
                    ->relationship(
                        'allowanceType',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('school_id', $this->getOwnerRecord()->school_id)->where('is_active', true)
                    )
                    ->required()
                    ->preload()
                    ->searchable()
                    ->helperText('Not in the list? Click + to add a new allowance type.')
                    ->createOptionModalHeading('New allowance type')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Allowance name')
                            ->placeholder('e.g. Housing, Transport, Responsibility')
                            ->required()
                            ->maxLength(100),
                        Toggle::make('is_taxable')
                            ->label('Taxable (PAYE applies)')
                            ->helperText('Most allowances are taxable employment income in Uganda.')
                            ->default(true),
                    ])
                    ->createOptionUsing(fn (array $data): int => AllowanceType::firstOrCreate(
                        ['school_id' => $this->getOwnerRecord()->school_id, 'name' => trim($data['name'])],
                        ['is_taxable' => (bool) ($data['is_taxable'] ?? true), 'is_active' => true],
                    )->getKey())
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Monthly amount')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Off = not paid, but kept on record.')
                    ->inline(false)
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('allowanceType.name')
                    ->label('Allowance')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
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
