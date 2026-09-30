<?php

namespace App\Filament\Widgets;

use App\Filament\App\Resources\AcademicYears\AcademicYearResource;
use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\App\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\App\Resources\Terms\TermResource;
use App\Filament\Pages\SchoolProfile;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Widgets\Widget;

/**
 * "Finish setting up": a school registers with only its name, category
 * and town, so this walks its administrator through the rest. It
 * disappears once every step is done.
 */
class SetupChecklist extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.setup-checklist';

    public static function canView(): bool
    {
        $user = auth()->user();

        return filament()->getCurrentPanel()?->getId() === 'app'
            && $user?->school_id
            && $user->hasRole('School Admin')
            && collect((new static)->steps())->contains('done', false);
    }

    /** @return list<array{title: string, text: string, url: string, done: bool}> */
    public function steps(): array
    {
        return once(function () {
            $id = $this->schoolId();
            $school = School::find($id);

            return [
                [
                    'title' => 'Complete your school profile',
                    'text' => 'Logo, motto, address and the head teacher\'s signature — they appear on receipts and report cards.',
                    'url' => SchoolProfile::getUrl(),
                    'done' => filled($school?->logo) && filled($school?->address),
                ],
                [
                    'title' => 'Set the academic year and term',
                    'text' => 'Fees, marks and reports are recorded against the current term.',
                    'url' => AcademicYear::where('school_id', $id)->exists() ? TermResource::getUrl() : AcademicYearResource::getUrl(),
                    'done' => Term::where('school_id', $id)->exists(),
                ],
                [
                    'title' => 'Add your classes',
                    'text' => 'Add a class for each year group. Streams are optional: add them only if you split a class, e.g. S.1 East, P.4 Blue.',
                    'url' => SchoolClassResource::getUrl(),
                    'done' => SchoolClass::where('school_id', $id)->exists(),
                ],
                [
                    'title' => 'Set up fees',
                    'text' => 'What each class pays this term, so balances and receipts work.',
                    'url' => FeeStructureResource::getUrl(),
                    'done' => FeeStructure::where('school_id', $id)->exists(),
                ],
            ];
        });
    }
}
