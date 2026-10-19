<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical copy of a library book, known by the accession number
 * stamped inside it (e.g. LIB-0042).
 *
 * @property int $id
 * @property int $school_id
 * @property int $library_book_id
 * @property string $accession_no
 * @property string $condition
 * @property string $status
 */
class LibraryCopy extends Model
{
    use Auditable;

    public const AVAILABLE = 'available';

    public const ON_LOAN = 'on_loan';

    public const LOST = 'lost';

    public const WITHDRAWN = 'withdrawn';

    public const STATUSES = [
        self::AVAILABLE => 'On the shelf',
        self::ON_LOAN => 'Borrowed',
        self::LOST => 'Lost',
        self::WITHDRAWN => 'Withdrawn',
    ];

    public const CONDITIONS = [
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
    ];

    protected $fillable = ['school_id', 'library_book_id', 'accession_no', 'condition', 'status'];

    /** @return BelongsTo<LibraryBook, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    /** @return HasMany<LibraryLoan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class);
    }

    /** @return HasOne<LibraryLoan, $this> the loan the copy is out on, if any */
    public function currentLoan(): HasOne
    {
        return $this->hasOne(LibraryLoan::class)->whereNull('returned_on')->whereNull('lost_on');
    }

    /** "LIB-0042 · Biology Bk 2" */
    public function label(): string
    {
        return $this->accession_no.' · '.$this->book?->title;
    }

    public static function formatNumber(int $number): string
    {
        return 'LIB-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /** The next free number for the school's LIB-#### sequence. */
    public static function nextNumber(int $schoolId): int
    {
        $highest = static::where('school_id', $schoolId)
            ->where('accession_no', 'like', 'LIB-%')
            ->pluck('accession_no')
            ->map(fn (string $no): int => (int) substr($no, 4))
            ->max();

        return ((int) $highest) + 1;
    }
}
