<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A title in the school library, e.g. "MK Primary Mathematics Bk 6", with
 * its numbered copies (LibraryCopy) and what a lost copy costs to replace.
 *
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property string|null $author
 * @property string $category
 * @property string|null $subject
 * @property string|null $class_level
 * @property string|null $isbn
 * @property string|null $publisher
 * @property string|null $shelf
 * @property string|null $price
 * @property string|null $notes
 * @property-read int|null $copies_count
 * @property-read int|null $available_count
 * @property-read int|null $out_count
 */
class LibraryBook extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'textbook' => 'Textbook',
        'reference' => 'Reference (dictionary, atlas…)',
        'novel' => 'Novel or story book',
        'pamphlet' => 'Pamphlet or past papers',
        'other' => 'Other',
    ];

    protected $fillable = ['school_id', 'title', 'author', 'category', 'subject', 'class_level', 'isbn', 'publisher', 'shelf', 'price', 'notes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<LibraryCopy, $this> */
    public function copies(): HasMany
    {
        return $this->hasMany(LibraryCopy::class);
    }

    /** @return HasManyThrough<LibraryLoan, LibraryCopy, $this> */
    public function loans(): HasManyThrough
    {
        return $this->hasManyThrough(LibraryLoan::class, LibraryCopy::class);
    }

    /**
     * Add copies numbered on from the school's last accession number, e.g.
     * LIB-0042, LIB-0043… Returns the new copies.
     *
     * @return list<LibraryCopy>
     */
    public function addCopies(int $count, string $condition = 'good'): array
    {
        $made = [];
        $next = LibraryCopy::nextNumber($this->school_id);

        for ($i = 0; $i < $count; $i++) {
            $made[] = $this->copies()->create([
                'school_id' => $this->school_id,
                'accession_no' => LibraryCopy::formatNumber($next + $i),
                'condition' => $condition,
                'status' => LibraryCopy::AVAILABLE,
            ]);
        }

        return $made;
    }
}
