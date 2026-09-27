<?php

namespace Database\Seeders;

use App\Models\PayeTaxBracket;
use Illuminate\Database\Seeder;

class PayeTaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // UGANDA — Monthly PAYE brackets (URA)
        // Taxable income = Gross - NSSF Employee
        // ========================================
        $ugandaBrackets = [
            ['min_amount' => 0,         'max_amount' => 235000,    'rate' => 0],       // 0% on first 235,000
            ['min_amount' => 235000,    'max_amount' => 335000,    'rate' => 10],      // 10% on 235,001 - 335,000
            ['min_amount' => 335000,    'max_amount' => 410000,    'rate' => 20],      // 20% on 335,001 - 410,000
            ['min_amount' => 410000,    'max_amount' => 10000000,  'rate' => 30],      // 30% on 410,001 - 10,000,000
            ['min_amount' => 10000000,  'max_amount' => null,      'rate' => 40],      // 40% above 10,000,000
        ];

        foreach ($ugandaBrackets as $bracket) {
            PayeTaxBracket::firstOrCreate(
                ['country' => 'UG', 'min_amount' => $bracket['min_amount']],
                $bracket
            );
        }

        // ========================================
        // KENYA — Monthly PAYE brackets (KRA)
        // ========================================
        $kenyaBrackets = [
            ['min_amount' => 0,       'max_amount' => 24000,   'rate' => 10],
            ['min_amount' => 24000,   'max_amount' => 32333,   'rate' => 25],
            ['min_amount' => 32333,   'max_amount' => 500000,  'rate' => 30],
            ['min_amount' => 500000,  'max_amount' => 800000,  'rate' => 32.5],
            ['min_amount' => 800000,  'max_amount' => null,    'rate' => 35],
        ];

        foreach ($kenyaBrackets as $bracket) {
            PayeTaxBracket::firstOrCreate(
                ['country' => 'KE', 'min_amount' => $bracket['min_amount']],
                $bracket
            );
        }

        // ========================================
        // TANZANIA — Monthly PAYE brackets (TRA)
        // ========================================
        $tanzaniaBrackets = [
            ['min_amount' => 0,        'max_amount' => 270000,   'rate' => 0],
            ['min_amount' => 270000,   'max_amount' => 520000,   'rate' => 8],
            ['min_amount' => 520000,   'max_amount' => 760000,   'rate' => 20],
            ['min_amount' => 760000,   'max_amount' => 1000000,  'rate' => 25],
            ['min_amount' => 1000000,  'max_amount' => null,     'rate' => 30],
        ];

        foreach ($tanzaniaBrackets as $bracket) {
            PayeTaxBracket::firstOrCreate(
                ['country' => 'TZ', 'min_amount' => $bracket['min_amount']],
                $bracket
            );
        }

        // ========================================
        // RWANDA — Monthly PAYE brackets (RRA)
        // ========================================
        $rwandaBrackets = [
            ['min_amount' => 0,       'max_amount' => 30000,    'rate' => 0],
            ['min_amount' => 30000,   'max_amount' => 100000,   'rate' => 20],
            ['min_amount' => 100000,  'max_amount' => null,     'rate' => 30],
        ];

        foreach ($rwandaBrackets as $bracket) {
            PayeTaxBracket::firstOrCreate(
                ['country' => 'RW', 'min_amount' => $bracket['min_amount']],
                $bracket
            );
        }
    }
}
