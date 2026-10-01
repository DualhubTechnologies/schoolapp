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
        $text = 'Each exam\'s weight is its share of the term result. O-Level (new curriculum): continuous assessment 20% + end of term 80%.';
        $problems = $this->weightProblems();

        if (! $problems) {
            return $text;
        }

        $list = collect($problems)->map(fn (float $total, string $label) => e(Str::before($label, ' (')).' '.($total + 0).'%')->implode(', ');

        return new HtmlString(e($text).'<br><span style="color:#b45309;font-weight:600">⚠ This term\'s weights do not add up to 100%: '.$list.'. Results are still worked out, in proportion, but check the weights.</span>');
    }

    /**
     * Weight totals that are not 100% for the term shown in the table.
     *
     * @return array<string, float>
     */
    public function weightProblems(): array
    {
        $schoolId = auth()->user()?->school_id;
        $termId = (int) ($this->tableFilters['term_id']['value'] ?? Term::current()?->getKey());

        return $schoolId && $termId ? Assessment::weightProblems($schoolId, $termId, SchoolType::curricula()) : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New exam')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id, 'status' => 'open']),
        ];
    }
}
