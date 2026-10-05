<?php

namespace App\Filament\App\Resources\SyllabusTopics\Pages;

use App\Filament\App\Resources\SyllabusTopics\SyllabusTopicResource;
use App\Filament\Pages\AssessTopics;
use App\Models\SyllabusTopic;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSyllabusTopics extends ManageRecords
{
    protected static string $resource = SyllabusTopicResource::class;

    public function getSubheading(): ?string
    {
        return 'Topics from each O-Level subject\'s NCDC syllabus. Teachers record learners\' levels (0–3) for them under Assess Topics.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assess')
                ->label('Assess topics')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->url(fn (): string => AssessTopics::getUrl()),
            Action::make('addMany')
                ->label('Add several topics')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->schema([
                    Select::make('subject_id')
                        ->label('Subject')
                        ->options(fn (): array => SyllabusTopicResource::subjectOptions())
                        ->required()
                        ->native(false),
                    Select::make('class_number')
                        ->label('Class')
                        ->options(SyllabusTopicResource::CLASSES)
                        ->required()
                        ->native(false),
                    Textarea::make('topics')
                        ->label('Topics, one per line')
                        ->placeholder("Topic 1: Introduction to Chemistry\nTopic 2: Experimental Chemistry\nTopic 3: States and changes of states of matter")
                        ->helperText('Copy them from the syllabus. A leading "Topic 1:", "T1" or "1." becomes the topic number.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $added = self::addTopics((int) $data['subject_id'], (int) $data['class_number'], (string) $data['topics']);

                    Notification::make()->title("{$added} ".str('topic')->plural($added).' added')->success()->send();
                }),
            CreateAction::make()
                ->label('Add topic')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id]),
        ];
    }

    /**
     * Add one topic per line, after the subject's existing topics for the
     * class. Lines already there (same name) are skipped.
     */
    public static function addTopics(int $subjectId, int $classNumber, string $text): int
    {
        $schoolId = auth()->user()?->school_id;
        $existing = SyllabusTopic::where('school_id', $schoolId)->where('subject_id', $subjectId)->where('class_number', $classNumber);
        $names = [];

        foreach ((clone $existing)->get() as $topic) {
            $names[] = mb_strtolower($topic->name);
        }

        $order = (int) (clone $existing)->max('sort_order');
        $added = 0;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $code = null;

            if (preg_match('/^(?:topic\s*|t)?(\d+)\s*[:.)\-–]?\s+(.+)$/iu', $line, $m)) {
                $code = 'T'.$m[1];
                $line = trim($m[2]);
            }

            if ($line === '' || in_array(mb_strtolower($line), $names, true)) {
                continue;
            }

            SyllabusTopic::create([
                'school_id' => $schoolId,
                'subject_id' => $subjectId,
                'class_number' => $classNumber,
                'code' => $code,
                'name' => mb_substr($line, 0, 255),
                'sort_order' => ++$order,
            ]);

            $names[] = mb_strtolower($line);
            $added++;
        }

        return $added;
    }
}
