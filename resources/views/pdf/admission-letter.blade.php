```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Admission Letter — {{ $student->name }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 16mm 13mm 16mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.45;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            width: 100%;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 9px;
            margin-bottom: 12px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 65px;
            vertical-align: middle;
        }

        .logo-cell img {
            width: 55px;
            height: 55px;
            object-fit: contain;
        }

        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 3px;
        }

        .school-meta {
            font-size: 8.5px;
            color: #6b7280;
            line-height: 1.35;
        }

        /* =========================
           DOCUMENT TITLE
        ========================= */

        .doc-title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #1e3a8a;

            background: #eff6ff;
            border: 1px solid #dbeafe;

            padding: 6px 8px;
            margin: 0 0 12px 0;
        }

        /* =========================
           REFERENCE
        ========================= */

        .ref-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9.5px;
        }

        .ref-row td {
            padding: 1px 0;
        }

        .ref-row .right {
            text-align: right;
            color: #6b7280;
        }

        /* =========================
           TEXT
        ========================= */

        .salutation {
            margin-bottom: 8px;
            font-size: 10.8px;
        }

        .body-text {
            margin-bottom: 9px;
            text-align: justify;
        }

        /* =========================
           STUDENT DETAILS
        ========================= */

        .details-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
            margin: 12px 0;
        }

        .details-box td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 9px;
            font-size: 9.8px;
            vertical-align: middle;
        }

        .details-box tr:last-child td {
            border-bottom: none;
        }

        .details-box .label {
            width: 34%;
            background: #f8fafc;
            color: #4b5563;
            font-weight: bold;
        }

        .details-box .value {
            color: #111827;
        }

        /* =========================
           STATUS
        ========================= */

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-radius: 8px;
        }

        .status-provisional {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        /* =========================
           FEES
        ========================= */

        .fee-highlight {
            width: 100%;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            margin: 12px 0;
            padding: 9px 12px;
        }

        .fee-highlight table {
            width: 100%;
            border-collapse: collapse;
        }

        .fee-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .fee-amount {
            font-size: 17px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .fee-description {
            text-align: right;
            vertical-align: middle;
            width: 42%;
            font-size: 8.5px;
            color: #6b7280;
            line-height: 1.35;
        }

        /* =========================
           CONDITIONS
        ========================= */

        .conditions {
            margin: 5px 0 10px 18px;
            padding-left: 12px;
        }

        .conditions li {
            margin-bottom: 4px;
            padding-left: 2px;
            font-size: 9.8px;
            line-height: 1.4;
        }

        /* =========================
           SIGNATURES
        ========================= */

        .signature-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
        }

        .signature-row td {
            width: 50%;
            vertical-align: bottom;
            padding-top: 20px;
        }

        .sig-line {
            border-top: 1px solid #6b7280;
            width: 175px;
            padding-top: 4px;
            font-size: 8.5px;
            color: #6b7280;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            margin-top: 20px;
            padding-top: 7px;
            border-top: 1px solid #e5e7eb;

            font-size: 7.5px;
            color: #9ca3af;
            text-align: center;
        }

        /* Prevent important blocks from splitting */
        .header,
        .doc-title,
        .details-box,
        .fee-highlight,
        .signature-row {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    {{-- =========================
         SCHOOL HEADER
    ========================== --}}

    <div class="header">

        <table>
            <tr>

                @if (($school->logo ?? false) && $logoPath)
                    <td class="logo-cell">
                        <img src="{{ $logoPath }}" alt="School Logo">
                    </td>
                @endif

                <td>
                    <div class="school-name">
                        {{ $school->name }}
                    </div>

                    <div class="school-meta">
                        @if ($school->address)
                            {{ $school->address }}
                        @endif

                        @if ($school->phone)
                            @if ($school->address) &nbsp; | &nbsp; @endif
                            Tel: {{ $school->phone }}
                        @endif

                        @if ($school->email)
                            @if ($school->phone || $school->address) &nbsp; | &nbsp; @endif
                            {{ $school->email }}
                        @endif
                    </div>
                </td>

            </tr>
        </table>

    </div>


    {{-- =========================
         TITLE
    ========================== --}}

    <div class="doc-title">
        Letter of Admission
    </div>


    {{-- =========================
         REFERENCE & DATE
    ========================== --}}

    <table class="ref-row">
        <tr>
            <td>
                <strong>Ref:</strong>
                ADM/{{ $student->admission_no }}/{{ now()->format('Y') }}
            </td>

            <td class="right">
                <strong>Date:</strong>
                {{ now()->format('j F Y') }}
            </td>
        </tr>
    </table>


    {{-- =========================
         SALUTATION
    ========================== --}}

    <div class="salutation">
        Dear <strong>{{ $student->name }}</strong>,
    </div>


    {{-- =========================
         INTRODUCTION
    ========================== --}}

    <div class="body-text">

        We are pleased to inform you that you have been offered a place at
        <strong>{{ $school->name }}</strong> for the academic year
        <strong>{{ $academicYear }}</strong>.

        Following a review of your application, you have been admitted to
        the class and section indicated below. Congratulations, and welcome
        to the school.

    </div>


    {{-- =========================
         STUDENT DETAILS
    ========================== --}}

    <table class="details-box">

        <tr>
            <td class="label">Student Name</td>
            <td class="value">{{ $student->name }}</td>
        </tr>

        <tr>
            <td class="label">Admission Number</td>
            <td class="value">{{ $student->admission_no }}</td>
        </tr>

        @if ($student->lin)
            <tr>
                <td class="label">Learner ID (LIN)</td>
                <td class="value">{{ $student->lin }}</td>
            </tr>
        @endif

        <tr>
            <td class="label">Class</td>
            <td class="value">
                {{ $student->schoolClass?->name ?? '—' }}
            </td>
        </tr>

        <tr>
            <td class="label">Section / Stream</td>
            <td class="value">
                {{ $student->section?->name ?? '—' }}
            </td>
        </tr>

        @if ($student->guardian)
            <tr>
                <td class="label">Parent / Guardian</td>
                <td class="value">
                    {{ $student->guardian->name }}
                </td>
            </tr>
        @endif

        <tr>
            <td class="label">Enrolment Status</td>
            <td class="value">

                <span class="status-badge status-{{ $student->enrolment_status }}">
                    {{
                        \App\Models\Student::ENROLMENT_STATUSES[
                            $student->enrolment_status
                        ]
                        ?? $student->enrolment_status
                    }}
                </span>

            </td>
        </tr>

    </table>


    {{-- =========================
         FEES
    ========================== --}}

    @if ($feeStructure)

        <div class="fee-highlight">

            <table>
                <tr>

                    <td>
                        <div class="fee-label">
                            Fees Payable —
                            {{ $feeStructure->term }},
                            {{ $feeStructure->academic_year }}
                        </div>

                        <div class="fee-amount">
                            {{ $feeStructure->formattedAmount() }}
                        </div>
                    </td>

                    @if ($feeStructure->description)

                        <td class="fee-description">
                            {{ $feeStructure->description }}
                        </td>

                    @endif

                </tr>
            </table>

        </div>

    @else

        <div class="body-text" style="color:#92400e;">
            <em>
                Fee details for this class have not yet been set.
                Please contact the bursar's office.
            </em>
        </div>

    @endif


    {{-- =========================
         OPENING DATE
    ========================== --}}

    <div class="body-text">

        The school term opens on

        <strong>
            {{
                $openingDate
                    ? \Illuminate\Support\Carbon::parse($openingDate)->format('l, j F Y')
                    : 'a date to be communicated'
            }}
        </strong>.

        To take up this place, kindly observe the following conditions:

    </div>


    {{-- =========================
         CONDITIONS
    ========================== --}}

    <ul class="conditions">

        <li>
            Report to the school on or before the opening date shown above.
        </li>

        <li>
            This admission is <strong>provisional</strong> until fees are
            paid or the place is confirmed by the school administration.
        </li>

        <li>
            Bring this letter and your previous academic records on the
            reporting day.
        </li>

        @if ($student->lin)

            <li>
                Your Learner Identification Number (LIN) is recorded as
                shown above.
            </li>

        @else

            <li>
                Please provide your Learner Identification Number (LIN)
                if available.
            </li>

        @endif

    </ul>


    {{-- =========================
         CLOSING
    ========================== --}}

    <div class="body-text">

        We look forward to welcoming you to
        <strong>{{ $school->name }}</strong>.

    </div>


    {{-- =========================
         SIGNATURES
    ========================== --}}

    <table class="signature-row">

        <tr>

            <td>
                <div class="sig-line">
                    Head Teacher / Principal
                </div>
            </td>

            <td>
                <div class="sig-line">
                    Official School Stamp
                </div>
            </td>

        </tr>

    </table>


    {{-- =========================
         FOOTER
    ========================== --}}

    <div class="footer">

        This is an official admission letter issued by
        {{ $school->name }}.

        Generated on
        {{ now()->format('j F Y, g:i a') }}.

    </div>

</body>
</html>
```
