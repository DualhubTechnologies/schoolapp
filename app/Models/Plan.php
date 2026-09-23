<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription tier. Every tier has every feature; tiers differ only
 * by how many active students and staff logins a school may have.
 */
class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_students',
        'max_users',
        'parent_student_login',
        'price_per_term',
        'price_per_year',
        'contact_sales',
        'is_trial',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_students' => 'integer',
            'max_users' => 'integer',
            'parent_student_login' => 'boolean',
            'price_per_term' => 'float',
            'price_per_year' => 'float',
            'contact_sales' => 'boolean',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceFor(?string $cycle): float
    {
        return match ($cycle) {
            'term' => $this->price_per_term,
            'year' => $this->price_per_year,
            default => 0,
        };
    }

    public static function limitLabel(?int $limit): string
    {
        return $limit === null ? 'Unlimited' : number_format($limit);
    }

    /** @return array<int, string> id => "Standard — 800 students · 25 users" */
    public static function options(bool $includeTrial = false): array
    {
        return static::where('is_active', true)
            ->when(! $includeTrial, fn ($q) => $q->where('is_trial', false))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Plan $p) => [$p->id => "{$p->name} — ".static::limitLabel($p->max_students).' students · '.static::limitLabel($p->max_users).' users'])
            ->all();
    }
}
