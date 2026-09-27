<?php

namespace App\Filament\App\Resources\TransportLearners\Pages;

use App\Filament\App\Resources\TransportLearners\TransportLearnerResource;
use App\Models\Student;
use App\Models\TransportRoute;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageTransportLearners extends ManageRecords
{
    protected static string $resource = TransportLearnerResource::class;

    public function getSubheading(): ?string
    {
        return 'Learners who use the school van. Their route\'s fare is added to their bill each term; everyone else is brought by a parent and pays nothing for transport.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addLearners')
                ->label('Add learners to a route')
                ->icon('heroicon-o-plus')
                ->modalWidth('xl')
                ->modalSubmitActionLabel('Add to route')
                ->schema([
                    Select::make('student_ids')
                        ->label('Learners')
                        ->multiple()
                        ->searchable()
                        ->placeholder('Type a learner\'s name or admission number')
                        ->required()
                        ->helperText('Learners already on the van are not listed; move them from the table instead.')
                        ->getSearchResultsUsing(fn (string $search): array => $this->learnersOffTheVan()
                            ->where(fn (Builder $query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('admission_no', 'like', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Student $student): array => [$student->id => TransportLearnerResource::learnerLabel($student)])
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => Student::with('schoolClass')
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Student $student): array => [$student->id => TransportLearnerResource::learnerLabel($student)])
                            ->all()),
                    ...TransportLearnerResource::routeFields(),
                ])
                ->action(function (array $data): void {
                    $updated = $this->learnersOffTheVan()
                        ->whereIn('id', $data['student_ids'])
                        ->get()
                        ->each(fn (Student $student) => $student->update([
                            'transport_route_id' => $data['transport_route_id'],
                            'transport_trip' => $data['transport_trip'],
                        ]))
                        ->count();

                    Notification::make()
                        ->title("{$updated} learners added to ".TransportRoute::whereKey($data['transport_route_id'])->value('name'))
                        ->body('Their fare is added when the term is billed.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Active learners in this school who are not on a route yet.
     *
     * @return Builder<Student>
     */
    protected function learnersOffTheVan(): Builder
    {
        return Student::query()
            ->with('schoolClass')
            ->where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->whereNull('transport_route_id')
            ->orderBy('name');
    }
}
