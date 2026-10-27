<?php

namespace App\Filament\App\Resources\Assessments\Pages;

use App\Filament\App\Resources\Assessments\AssessmentResource;
use App\Models\Assessment;
use App\Models\Term;
use App\Support\SchoolType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageAssessments extends ManageRecords
{
    protected static string $resource = AssessmentResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $text = 'Only the exams you set count. Each one\'s weight is its share of the term result, scaled to 100%: '
            .'a school that gives only an End of Term exam gets End of Term = 100%.';
        $breakdown = $this->termBreakdown();

        if (! $breakdown) {
            return $text;
        }

        $lines = collect($breakdown)->map(fn (array $parts, string $label): string => '<strong>'.e(Str::before($label, ' (')).':</strong> '
            .($parts === [] ? 'all exams count equally' : collect($parts)->map(fn (float $share, string $name): string => e($name).' '.($share + 0).'%')->implode(' · ')));

        return new HtmlString(e($text).'<br>This term: '.$lines->implode(' &nbsp;|&nbsp; '));
    }

    /**
     * How each level's result is made up this term (the term shown in the table).
     *
     * @return array<string, array<string, float>>
     */
    public function termBreakdown(): array
    {
        $schoolId = auth()->user()?->school_id;
        $termId = (int) ($this->tableFilters['term_id']['value'] ?? Term::current()?->getKey());

        return $schoolId && $termId ? Assessment::termBreakdown($schoolId, $termId, SchoolType::curricula()) : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New exam')
                ->visible(fn (): bool => AssessmentResource::canCreate())
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id, 'status' => 'open']),
        ];
    }
}
