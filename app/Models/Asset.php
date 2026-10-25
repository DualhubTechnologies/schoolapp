<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Something the school owns and keeps: desks, computers, beds, a
 * generator. Each gets a tag number (AST-0001) to paint or stick on it,
 * and the register shows where it is, who looks after it and its state.
 * Several identical items (40 desks in S.1 East) can share one tag.
 *
 * @property int $id
 * @property int $school_id
 * @property int $tag_seq
 * @property string $tag_no
 * @property string $name
 * @property string $category
 * @property int $quantity
 * @property string|null $location
 * @property string $condition
 * @property string|null $assigned_to
 * @property string|null $serial_no
 * @property Carbon|null $acquired_on
 * @property string|null $cost
 * @property string|null $supplier
 * @property Carbon|null $disposed_on
 * @property string|null $notes
 */
class Asset extends Model
{
    use Auditable;

    protected $fillable = [
        'school_id', 'name', 'category', 'quantity', 'location', 'condition', 'assigned_to',
        'serial_no', 'acquired_on', 'cost', 'supplier', 'disposed_on', 'notes',
    ];

    public const CATEGORIES = [
        'furniture' => 'Furniture',
        'ict' => 'Computers & electronics',
        'kitchen' => 'Kitchen equipment',
        'boarding' => 'Boarding (beds, mattresses)',
        'laboratory' => 'Laboratory equipment',
        'sports' => 'Sports equipment',
        'books' => 'Textbooks & library',
        'vehicles' => 'Vehicles',
        'machinery' => 'Machinery & generators',
        'buildings' => 'Buildings & fixtures',
        'other' => 'Other',
    ];

    public const CONDITIONS = [
        'new' => 'New',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Needs repair',
        'broken' => 'Broken',
        'lost' => 'Lost',
        'disposed' => 'Disposed of',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquired_on' => 'date',
            'disposed_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // The next tag number for the school: AST-0001, AST-0002...
        static::creating(function (Asset $asset) {
            DB::transaction(function () use ($asset) {
                $last = static::where('school_id', $asset->school_id)->lockForUpdate()->max('tag_seq');
                $asset->tag_seq = ((int) $last) + 1;
                $asset->tag_no = 'AST-'.str_pad((string) $asset->tag_seq, 4, '0', STR_PAD_LEFT);
            });
        });
    }

    /**
     * Still with the school: not lost or disposed of.
     *
     * @param  Builder<Asset>  $query
     */
    public function scopeInUse(Builder $query): void
    {
        $query->whereNotIn('condition', ['lost', 'disposed']);
    }

    public function conditionColor(): string
    {
        return match ($this->condition) {
            'new', 'good' => 'success',
            'fair' => 'info',
            'poor' => 'warning',
            default => 'danger',
        };
    }
}
