<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayeTaxBracket extends Model
{
    protected $fillable = [
        'country',
        'min_amount',
        'max_amount',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate' => 'decimal:4',
        ];
    }

    /**
     * Calculate PAYE tax for a given monthly taxable income in a given country.
     */
    public static function calculatePaye(float $taxableIncome, string $country = 'UG'): float
    {
        $brackets = self::where('country', $country)
            ->orderBy('min_amount')
            ->get();

        $totalTax = 0;

        foreach ($brackets as $bracket) {
            if ($taxableIncome <= $bracket->min_amount) {
                break;
            }

            $upperLimit = $bracket->max_amount ?? $taxableIncome;
            $taxableInBracket = min($taxableIncome, $upperLimit) - $bracket->min_amount;

            if ($taxableInBracket > 0) {
                $totalTax += $taxableInBracket * ($bracket->rate / 100);
            }
        }

        return round($totalTax, 2);
    }
}
