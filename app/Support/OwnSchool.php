<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Limits a query to the signed-in user's school. For the option lists of
 * table filters built from a relationship (Class, Section, Residency...),
 * which otherwise list every school's records: a secondary school would
 * see another school's "Baby Class", and one school's class names would
 * show to another.
 */
class OwnSchool
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('school_id'), auth()->user()?->school_id);
    }
}
