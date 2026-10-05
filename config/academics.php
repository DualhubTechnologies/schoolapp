<?php

/*
 * Ugandan curriculum defaults. A school gets its own copy of these when it
 * clicks "Set up Uganda curriculum" -- after that, everything (subjects,
 * grading bands, combinations) is edited in the app, not here.
 *
 * Scores are always handled as percentages. An assessment marked out of
 * something else (e.g. an Activity of Integration scored out of 3) is
 * converted using its "out of" value.
 */

return [

    'curricula' => [
        'nursery' => 'Nursery (ECD)',
        'primary' => 'Primary',
        'o_level' => 'O-Level (lower secondary, new curriculum)',
        'a_level' => 'A-Level',
    ],

    /*
     * The classes each curriculum starts with when a school takes the
     * recommended setup. No streams: a class works on its own, and a school
     * that splits a class adds streams later.
     */
    'classes' => [
        'nursery' => ['Baby Class', 'Middle Class', 'Top Class'],
        'primary' => ['P.1', 'P.2', 'P.3', 'P.4', 'P.5', 'P.6', 'P.7'],
        'o_level' => ['S.1', 'S.2', 'S.3', 'S.4'],
        'a_level' => ['S.5', 'S.6'],
    ],

    /*
     * Ministry of Education and Sports term dates, one year at a time:
     * [term name, opens, closes]. Update each January from
     * https://www.education.go.ug/school-calendars/. A year missing here
     * falls back to 'calendar_pattern' (the usual shape of the year).
     */
    'calendar' => [
        2026 => [
            ['Term 1', '2026-02-02', '2026-05-01'],
            ['Term 2', '2026-05-25', '2026-08-22'],
            ['Term 3', '2026-09-14', '2026-12-04'],
        ],
    ],

    'calendar_pattern' => [
        ['Term 1', '02-01', '04-30'],
        ['Term 2', '05-25', '08-21'],
        ['Term 3', '09-14', '12-04'],
    ],

    'assessment_types' => [
        'bot' => 'Beginning of Term',
        'mot' => 'Mid-Term',
        'eot' => 'End of Term',
        'ca' => 'Continuous Assessment / Activity of Integration',
        'project' => 'Project work',
        'topics' => 'Topic assessment (0–3, filled from Assess Topics)',
        'other' => 'Other test',
    ],

    /*
     * Suggested weights (% of the term result) when an exam is created.
     * New lower-secondary curriculum: school-based assessment 20% (AOIs
     * 10% + project work 10%), end-of-term 80%, mirroring UNEB's 20/80
     * split at UCE.
     */
    'default_weights' => [
        'primary' => ['bot' => 20, 'mot' => 30, 'eot' => 50, 'ca' => 0, 'other' => 0],
        'nursery' => ['bot' => 20, 'mot' => 30, 'eot' => 50, 'ca' => 0, 'other' => 0],
        'o_level' => ['bot' => 0, 'mot' => 0, 'eot' => 80, 'ca' => 10, 'project' => 10, 'other' => 0],
        'a_level' => ['bot' => 20, 'mot' => 30, 'eot' => 50, 'ca' => 0, 'other' => 0],
    ],

    /*
     * Subjects. 'compulsory_in' lists the class numbers (P1 = 1 ... S6 = 6)
     * where the subject is compulsory; 'offered_in' where it is taught at
     * all. Anything offered but not compulsory is an elective there.
     */
    'subjects' => [

        // Nursery / ECD learning areas (Baby, Middle and Top class).
        'nursery' => [
            ['name' => 'Language & Communication', 'short' => 'LANG'],
            ['name' => 'Number Concepts', 'short' => 'NUM'],
            ['name' => 'Reading Readiness', 'short' => 'READ'],
            ['name' => 'Social Development & Environment', 'short' => 'SOC'],
            ['name' => 'Health Habits', 'short' => 'HLTH'],
            ['name' => 'Creative Arts & Music', 'short' => 'ART'],
            ['name' => 'Religious Education', 'short' => 'RE'],
        ],

        'primary' => [
            // Lower primary thematic curriculum (P1–P3)
            ['name' => 'Literacy I', 'short' => 'LIT1', 'offered_in' => [1, 2, 3]],
            ['name' => 'Literacy II', 'short' => 'LIT2', 'offered_in' => [1, 2, 3]],
            ['name' => 'Numeracy', 'short' => 'NUM', 'offered_in' => [1, 2, 3]],
            ['name' => 'Oral English', 'short' => 'OENG', 'offered_in' => [1, 2, 3]],
            // Upper primary (P4–P7): the four PLE subjects are "core"
            ['name' => 'English', 'short' => 'ENG', 'category' => 'core', 'offered_in' => [4, 5, 6, 7]],
            ['name' => 'Mathematics', 'short' => 'MTC', 'category' => 'core', 'offered_in' => [4, 5, 6, 7]],
            ['name' => 'Integrated Science', 'short' => 'SCI', 'category' => 'core', 'offered_in' => [4, 5, 6, 7]],
            ['name' => 'Social Studies', 'short' => 'SST', 'category' => 'core', 'offered_in' => [4, 5, 6, 7]],
            // Throughout
            ['name' => 'Religious Education', 'short' => 'RE', 'offered_in' => [1, 2, 3, 4, 5, 6, 7]],
            ['name' => 'Local Language', 'short' => 'LL', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Kiswahili', 'short' => 'KIS', 'offered_in' => [4, 5, 6, 7], 'compulsory_in' => []],
            ['name' => 'Creative Arts & Physical Education', 'short' => 'CAPE', 'offered_in' => [1, 2, 3, 4, 5, 6, 7], 'compulsory_in' => []],
        ],

        // New lower-secondary curriculum (NCDC): 11 compulsory subjects in
        // S1–S2; 7 compulsory in S3–S4 plus one or two electives.
        'o_level' => [
            ['name' => 'English Language', 'short' => 'ENG', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Mathematics', 'short' => 'MTC', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Physics', 'short' => 'PHY', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Chemistry', 'short' => 'CHE', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Biology', 'short' => 'BIO', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Geography', 'short' => 'GEO', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'History & Political Education', 'short' => 'HPE', 'offered_in' => [1, 2, 3, 4]],
            ['name' => 'Religious Education', 'short' => 'RE', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => [1, 2]],
            ['name' => 'Entrepreneurship', 'short' => 'ENT', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => [1, 2]],
            ['name' => 'Kiswahili', 'short' => 'KIS', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => [1, 2]],
            ['name' => 'Physical Education', 'short' => 'PE', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => [1, 2]],
            ['name' => 'Agriculture', 'short' => 'AGR', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Information & Communication Technology', 'short' => 'ICT', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Literature in English', 'short' => 'LIT', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Art & Design', 'short' => 'ART', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Performing Arts', 'short' => 'PFA', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Nutrition & Food Technology', 'short' => 'NFT', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Technology & Design', 'short' => 'TD', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'French', 'short' => 'FRE', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
            ['name' => 'Luganda', 'short' => 'LUG', 'offered_in' => [1, 2, 3, 4], 'compulsory_in' => []],
        ],

        'a_level' => [
            ['name' => 'Mathematics', 'short' => 'MTC', 'category' => 'principal'],
            ['name' => 'Physics', 'short' => 'PHY', 'category' => 'principal'],
            ['name' => 'Chemistry', 'short' => 'CHE', 'category' => 'principal'],
            ['name' => 'Biology', 'short' => 'BIO', 'category' => 'principal'],
            ['name' => 'Agriculture', 'short' => 'AGR', 'category' => 'principal'],
            ['name' => 'Geography', 'short' => 'GEO', 'category' => 'principal'],
            ['name' => 'Economics', 'short' => 'ECO', 'category' => 'principal'],
            ['name' => 'History', 'short' => 'HIS', 'category' => 'principal'],
            ['name' => 'Literature in English', 'short' => 'LIT', 'category' => 'principal'],
            ['name' => 'Divinity', 'short' => 'DIV', 'category' => 'principal'],
            ['name' => 'Islamic Religious Education', 'short' => 'IRE', 'category' => 'principal'],
            ['name' => 'Entrepreneurship Education', 'short' => 'ENT', 'category' => 'principal'],
            ['name' => 'Fine Art', 'short' => 'ART', 'category' => 'principal'],
            ['name' => 'Kiswahili', 'short' => 'KIS', 'category' => 'principal'],
            ['name' => 'Luganda', 'short' => 'LUG', 'category' => 'principal'],
            ['name' => 'French', 'short' => 'FRE', 'category' => 'principal'],
            ['name' => 'General Paper', 'short' => 'GP', 'category' => 'subsidiary', 'compulsory' => true],
            ['name' => 'Subsidiary Mathematics', 'short' => 'SMTC', 'category' => 'subsidiary'],
            ['name' => 'Subsidiary ICT', 'short' => 'SICT', 'category' => 'subsidiary'],
        ],
    ],

    /*
     * Common A-Level combinations: code => three principal subjects.
     * Combinations with principal Mathematics take Subsidiary ICT; the
     * rest take Subsidiary Mathematics (changeable per combination).
     */
    'combinations' => [
        'PCM' => ['Physics', 'Chemistry', 'Mathematics'],
        'PCB' => ['Physics', 'Chemistry', 'Biology'],
        'BCM' => ['Biology', 'Chemistry', 'Mathematics'],
        'BCA' => ['Biology', 'Chemistry', 'Agriculture'],
        'PEM' => ['Physics', 'Economics', 'Mathematics'],
        'MEG' => ['Mathematics', 'Economics', 'Geography'],
        'MEE' => ['Mathematics', 'Economics', 'Entrepreneurship Education'],
        'HEG' => ['History', 'Economics', 'Geography'],
        'HEL' => ['History', 'Economics', 'Literature in English'],
        'HED' => ['History', 'Economics', 'Divinity'],
        'HGL' => ['History', 'Geography', 'Literature in English'],
        'HLD' => ['History', 'Literature in English', 'Divinity'],
        'DEG' => ['Divinity', 'Economics', 'Geography'],
        'AKR' => ['Fine Art', 'Kiswahili', 'Divinity'],
    ],

    /*
     * Grading bands: [grade, min %, max %, value, descriptor].
     * 'value' is the aggregate number (primary) or the points (A-Level).
     * Schools set their own cut-offs -- edit them under Grading Scales.
     */
    'grading' => [
        'primary' => [
            'subject' => [
                ['D1', 80, 100, 1, 'Distinction'],
                ['D2', 70, 79.99, 2, 'Distinction'],
                ['C3', 65, 69.99, 3, 'Credit'],
                ['C4', 60, 64.99, 4, 'Credit'],
                ['C5', 55, 59.99, 5, 'Credit'],
                ['C6', 50, 54.99, 6, 'Credit'],
                ['P7', 45, 49.99, 7, 'Pass'],
                ['P8', 40, 44.99, 8, 'Pass'],
                ['F9', 0, 39.99, 9, 'Failure'],
            ],
        ],
        'nursery' => [
            'subject' => [
                ['Excellent', 80, 100, 1, 'Excellent'],
                ['Very good', 70, 79.99, 2, 'Very good'],
                ['Good', 60, 69.99, 3, 'Good'],
                ['Fair', 50, 59.99, 4, 'Fair'],
                ['Needs support', 0, 49.99, 5, 'Needs more support'],
            ],
        ],
        // NCDC achievement levels (score out of 3 → %: A 2.5–3.0, B 2.01–
        // 2.49, C 1.5–2.0, D 0.9–1.49, E below 0.9).
        'o_level' => [
            'subject' => [
                ['A', 83.33, 100, 3, 'Exceptional'],
                ['B', 67, 83.32, 2.5, 'Outstanding'],
                ['C', 50, 66.99, 2, 'Satisfactory'],
                ['D', 30, 49.99, 1.5, 'Basic'],
                ['E', 0, 29.99, 1, 'Elementary'],
            ],
        ],
        'a_level' => [
            'principal' => [
                ['A', 80, 100, 6, 'Excellent'],
                ['B', 70, 79.99, 5, 'Very good'],
                ['C', 60, 69.99, 4, 'Good'],
                ['D', 50, 59.99, 3, 'Fair'],
                ['E', 45, 49.99, 2, 'Pass'],
                ['O', 35, 44.99, 1, 'Subsidiary pass'],
                ['F', 0, 34.99, 0, 'Fail'],
            ],
            'subsidiary' => [
                ['D1', 80, 100, 1, 'Pass'],
                ['D2', 70, 79.99, 1, 'Pass'],
                ['C3', 65, 69.99, 1, 'Pass'],
                ['C4', 60, 64.99, 1, 'Pass'],
                ['C5', 55, 59.99, 1, 'Pass'],
                ['C6', 50, 54.99, 1, 'Pass'],
                ['P7', 45, 49.99, 0, 'Fail'],
                ['P8', 40, 44.99, 0, 'Fail'],
                ['F9', 0, 39.99, 0, 'Fail'],
            ],
        ],
    ],

    /*
     * Primary divisions from the aggregate of the four core subjects, as
     * in PLE: [division, lowest aggregate, highest aggregate].
     */
    'primary_divisions' => [
        ['Division 1', 4, 12],
        ['Division 2', 13, 23],
        ['Division 3', 24, 29],
        ['Division 4', 30, 34],
        ['Ungraded', 35, 36],
    ],

    /*
     * Suggested class-teacher comments by average %, for the "fill
     * comments" button. Always editable before printing.
     */
    // How many electives a learner takes on top of the compulsory subjects
    // (NCDC lower secondary: one or two, in S1-S2 and in S3-S4). The
    // Subject Choices page flags learners outside this range.
    'electives' => [
        'o_level' => ['min' => 1, 'max' => 2],
    ],

    'comments' => [
        80 => 'Excellent work. Keep it up!',
        70 => 'Very good performance. Aim even higher.',
        60 => 'Good work. More effort will bring better results.',
        50 => 'Fair performance. Needs to work harder.',
        40 => 'Below average. Needs serious effort and support.',
        0 => 'Poor performance. Needs close support from teachers and parents.',
    ],

];
