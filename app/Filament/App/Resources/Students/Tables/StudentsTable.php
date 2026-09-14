<?php

namespace App\Filament\App\Resources\Students\Tables;

use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\StudentDocumentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=random'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lin')
                    ->label('LIN')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('—'),
                TextColumn::make('gender')
                    ->formatStateUsing(fn (?string $state) => Student::GENDERS[$state] ?? '—')
                    ->toggleable(),
                TextColumn::make('guardian.name')
                    ->label('Guardian')
                    ->placeholder('Not linked')
                    ->searchable(),
                TextColumn::make('guardian.phone')
                    ->label('Guardian phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('enrolment_status')
                    ->label('Enrolment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Student::ENROLMENT_STATUSES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'provisional' => 'warning',
                        default => 'gray',
                    }),
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
                    ->toggleable(),
                TextColumn::make('date_of_birth')
                    ->label('D.O.B.')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('admission_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('house.name')
                    ->label('House')
                    ->badge()
                    ->searchable(),
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
            ])
            ->recordActions([
                ActionGroup::make([

                    // ── Admission letter PDF ──
                    Action::make('admissionLetter')
                        ->label('Admission letter')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->form([
                            Select::make('term')
                                ->options([
                                    'Term 1' => 'Term 1',
                                    'Term 2' => 'Term 2',
                                    'Term 3' => 'Term 3',
                                ])
                                ->default('Term 1')
                                ->required(),
                            TextInput::make('academic_year')
                                ->label('Academic year')
                                ->default(fn () => (string) now()->year)
                                ->required(),
                            DatePicker::make('opening_date')
                                ->label('School opening date')
                                ->required(),
                        ])
                        ->action(function ($record, array $data): StreamedResponse {
                            $pdf = app(StudentDocumentService::class)->admissionLetter(
                                $record,
                                $data['term'],
                                $data['academic_year'],
                                $data['opening_date'],
                            );

                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'admission-letter-' . $record->admission_no . '.pdf',
                            );
                        }),

                    // ── Student profile PDF ──
                    Action::make('profile')
                        ->label('Print profile')
                        ->icon('heroicon-o-identification')
                        ->color('gray')
                        ->action(function ($record): StreamedResponse {
                            $pdf = app(StudentDocumentService::class)->profile($record);

                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'profile-' . $record->admission_no . '.pdf',
                            );
                        }),

                    // ── Record payment (auto-confirms enrolment) ──
                    Action::make('recordPayment')
                        ->label('Record payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->form([
                            TextInput::make('amount')
                                ->numeric()
                                ->prefix('UGX')
                                ->required()
                                ->minValue(0),
                            DatePicker::make('paid_on')
                                ->default(now())
                                ->required(),
                            Select::make('method')
                                ->options(StudentPayment::METHODS)
                                ->default('cash')
                                ->required(),
                            TextInput::make('reference')
                                ->label('Receipt / reference'),
                            Textarea::make('notes')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data): void {
                            StudentPayment::create([
                                'school_id' => $record->school_id,
                                'student_id' => $record->id,
                                'amount' => $data['amount'],
                                'paid_on' => $data['paid_on'],
                                'method' => $data['method'],
                                'reference' => $data['reference'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'recorded_by' => auth()->user()?->name,
                            ]);
                            // StudentPayment::created hook confirms enrolment if not already.

                            Notification::make()
                                ->title('Payment recorded')
                                ->body($record->fresh()->isConfirmed()
                                    ? 'Student is now a confirmed full student.'
                                    : 'Payment saved.')
                                ->success()
                                ->send();
                        }),

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
                                ->body($record->name . ' is now a full student.')
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
                    ->paginationPageOptions([5, 10, 25, 50])
        ->defaultPaginationPageOption(5);
            
    }
}