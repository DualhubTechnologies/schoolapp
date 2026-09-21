<?php

/*
 * Ugandan payroll rules. Review these with the school's accountant each
 * financial year (1 July) -- rates are set by Parliament and URA.
 *
 * PAYE bands live in the paye_tax_brackets table (PayeTaxBracketSeeder).
 */

return [

    // NSSF Act: 5% from the employee, 10% from the employer, on gross pay.
    'nssf' => [
        'employee_rate' => 5,
        'employer_rate' => 10,
    ],

    // Employee NSSF is NOT an allowable deduction for PAYE in Uganda: PAYE
    // is charged on gross taxable pay. Only change this on professional
    // tax advice.
    'deduct_nssf_before_paye' => false,

    // Salary arrears are employment income: taxed (PAYE) and subject to
    // NSSF in the month they are paid.
    'arrears_are_taxable' => true,

    /*
     * Local Service Tax (Local Governments Act). An ANNUAL amount set by
     * monthly pay, collected by the employer in equal instalments over the
     * first four months of the financial year and remitted to the local
     * government.
     *
     * Bands: [monthly gross above => annual LST].
     */
    'lst' => [
        'enabled' => true,
        'months' => [7, 8, 9, 10], // July – October
        'bands' => [
            0 => 0,
            100_000 => 5_000,
            200_000 => 10_000,
            300_000 => 20_000,
            400_000 => 30_000,
            500_000 => 40_000,
            600_000 => 60_000,
            700_000 => 70_000,
            800_000 => 80_000,
            900_000 => 90_000,
            1_000_000 => 100_000,
        ],
    ],

];
