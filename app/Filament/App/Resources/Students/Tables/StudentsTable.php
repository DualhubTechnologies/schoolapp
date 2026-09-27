<?php

namespace App\Filament\App\Resources\Students\Tables;

use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\StudentAccount;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Support\PrivateFiles;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load what the combined cells read, so a page of
            // students is a handful of queries, not one per row.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['section', 'guardian', 'transportRoute']))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->disk(PrivateFiles::DISK)
                    ->visibility('private')
                    ->circular()
                    // Local placeholder: works offline and doesn't send
                    // student names to a third-party avatar service.
                    ->defaultImageUrl(asset('images/student-avatar.svg')),

                TextColumn::make('admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Admission number copied'),

                // Name, with gender and age underneath.
                TextColumn::make('name')
                    ->label('Student')
                    ->weight(FontWeight::SemiBold)
                    ->searchable(['name', 'first_name', 'last_name'])
                    ->sortable()
                    ->description(fn (Student $record): ?string => collect([
                        Student::GENDERS[$record->gender] ?? null,
                        $record->age !== null ? "{$record->age} yrs" : null,
                    ])->filter()->implode(' · ') ?: null),

                // Class and stream in one badge: "S1 · A".
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Student $record): string => $record->section
                        ? "{$state} · {$record->section->name}"
                        : (string) $state)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('residencyType.name')
                    ->label('Residency')
                    ->badge()
                    ->color(fn (Student $record) => $record->residencyType?->badgeColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('transportRoute.name')
                    ->label('Van')
                    ->placeholder('Parent')
                    ->description(fn (Student $record): ?string => $record->transport_trip === 'one_way' ? 'One way' : null)
                    ->toggleable(),

                // Guardian, with their phone underneath. Search matches either.
                TextColumn::make('guardian.name')
                    ->label('Guardian')
                    ->placeholder('Not linked')
                    ->description(fn (Student $record): ?string => $record->guardian?->phone)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'guardian',
                        fn (Builder $guardian) => $guardian
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"),
                    )),

                TextColumn::make('enrolment_status')
                    ->label('Enrolment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Student::ENROLMENT_STATUSES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'provisional' => 'warning',
                        default => 'gray',
                    }),

                // Hidden by default: the list is already filtered to active
                // students. Shown when the filter is changed via the toggle.
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Student::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'graduated' => 'info',
                        'withdrawn' => 'danger',
                        'transferred' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Available from the column picker ──
                TextColumn::make('house.name')
                    ->label('House')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lin')
                    ->label('LIN')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_of_birth')
                    ->label('D.O.B.')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('admission_date')
                    ->label('Admitted')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('school.name')
                    ->label('School')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->relationship('schoolClass', 'name')
                    ->preload(),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->preload(),
                SelectFilter::make('enrolment_status')
                    ->label('Enrolment')
                    ->options(Student::ENROLMENT_STATUSES),
                SelectFilter::make('status')
                    ->options(Student::STATUSES)
                    ->default('active'),
                SelectFilter::make('transport_route_id')
                    ->label('Van')
                    ->options(fn (): array => ['none' => 'Brought by parent'] + static::routeOptions())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'none' => $query->whereNull('transport_route_id'),
                        default => $query->where('transport_route_id', $data['value']),
                    }),
            ])
            ->recordActions([
                ActionGroup::make([

                    // ── Admission letter PDF ── one click, opens in a new
                    // tab: fees shown are whatever applies to the student
                    // for the school's current term, so there is nothing
                    // to fill in first.
                    Action::make('admissionLetter')
                        ->label('Admission letter')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(fn ($record) => route('filament.app.students.admission-letter', $record))
                        ->openUrlInNewTab(),

                    // ── Student profile PDF ──
                    Action::make('profile')
                        ->label('Print profile')
                        ->icon('heroicon-o-identification')
                        ->color('gray')
                        ->url(fn ($record) => route('filament.app.students.profile', $record))
                        ->openUrlInNewTab(),

                    // ── Payments go through Receive Payment, which issues a
                    //    numbered receipt (and auto-confirms enrolment) ──
                    Action::make('recordPayment')
                        ->label('Receive payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->url(fn ($record) => ReceivePayment::getUrl(['student' => $record->getKey()])),

                    Action::make('feeAccount')
                        ->label('Fee account')
                        ->icon('heroicon-o-book-open')
                        ->url(fn ($record) => StudentAccount::getUrl(['student' => $record->getKey()])),

                    // ── Manual confirm (registrar) — hidden once confirmed ──
                    Action::make('confirmEnrolment')
                        ->label('Confirm as full student')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => ! $record->isConfirmed())
                        ->requiresConfirmation()
                        ->modalHeading('Confirm student enrolment')
                        ->modalDescription('This marks the student as a full member of the school. Use when the place is confirmed administratively, without waiting for payment.')
                        ->action(function ($record): void {
                            $record->confirmEnrolment('manual');

                            Notification::make()
                                ->title('Student confirmed')
                                ->body($record->name.' is now a full student.')
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Put a group on a van route (or take them off) in one go,
                    // e.g. everyone from Kakiri at the start of term.
                    BulkAction::make('setVan')
                        ->label('Set school van')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Select::make('transport_route_id')
                                ->label('School van')
                                ->options(fn (): array => static::routeOptions())
                                ->placeholder('Brought by parent (no van)'),
                            Select::make('transport_trip')
                                ->label('Uses the van')
                                ->options(TransportRoute::TRIPS)
                                ->default('both')
                                ->selectablePlaceholder(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $student) {
                                if ($student instanceof Student) {
                                    $student->update([
                                        'transport_route_id' => $data['transport_route_id'] ?: null,
                                        'transport_trip' => $data['transport_trip'] ?? 'both',
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title($data['transport_route_id'] ? 'Van route set' : 'Taken off the van')
                                ->body($records->count().' learners updated. The fare is added when the term is billed.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * The school's van routes that are in use, as filter and form options.
     *
     * @return array<int, string>
     */
    protected static function routeOptions(): array
    {
        return TransportRoute::where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
