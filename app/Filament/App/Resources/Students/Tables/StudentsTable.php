<?php

namespace App\Filament\App\Resources\Students\Tables;

use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\StudentAccount;
use App\Filament\Pages\StudentIdCards;
use App\Models\Student;
use App\Support\Modules;
use App\Support\OwnSchool;
use App\Support\PrivateFiles;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No learners yet')
            ->emptyStateDescription('Add learners one at a time with “New student”, or all at once: download the CSV template, fill it in Excel and use “Import students”.')
            // Eager-load what the combined cells read, so a page of
            // students is a handful of queries, not one per row.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['section', 'guardian']))
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
                TextColumn::make('schoolpay_code')
                    ->label('SchoolPay code')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
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
                    ->relationship('schoolClass', 'name', fn (Builder $query) => OwnSchool::scope($query))
                    ->preload(),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name', fn (Builder $query) => OwnSchool::scope($query))
                    ->preload(),
                SelectFilter::make('enrolment_status')
                    ->label('Enrolment')
                    ->options(Student::ENROLMENT_STATUSES),
                SelectFilter::make('status')
                    ->options(Student::STATUSES)
                    ->default('active'),
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

                    // ── ID card preview -- opens Student ID Cards for this
                    //    one learner rather than printing straight away, so
                    //    missing details are caught before anything is cut ──
                    Action::make('idCard')
                        ->label('ID card')
                        ->icon('heroicon-o-credit-card')
                        ->color('gray')
                        ->visible(fn (): bool => Modules::allows('id_cards'))
                        ->url(fn ($record) => StudentIdCards::getUrl(['id' => $record->getKey()]))
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
                    // ->url() does not receive the selected records reliably
                    // on a bulk action, so this redirects instead.
                    BulkAction::make('idCards')
                        ->label('Print ID cards')
                        ->icon('heroicon-o-credit-card')
                        ->color('gray')
                        ->visible(fn (): bool => Modules::allows('id_cards'))
                        ->action(fn (Collection $records) => redirect(StudentIdCards::getUrl(['ids' => $records->pluck('id')->implode(',')]))),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
