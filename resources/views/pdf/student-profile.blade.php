<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Profile — {{ $student->name }}</title>
    <style>
        @page { margin: 36px 44px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .header table { width: 100%; }
        .logo-cell { width: 64px; vertical-align: middle; }
        .logo-cell img { width: 56px; height: 56px; object-fit: contain; }
        .school-name { font-size: 18px; font-weight: bold; color: #1e3a8a; }
        .school-meta { font-size: 9px; color: #6b7280; }
        .doc-tag { text-align: right; font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }

        /* Identity band */
        .identity { width: 100%; margin-bottom: 20px; }
        .identity td { vertical-align: top; }
        .photo-cell { width: 120px; }
        .photo-frame {
            width: 110px; height: 130px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            background: #f9fafb;
        }
        .photo-frame img { width: 110px; height: 130px; object-fit: cover; }
        .photo-placeholder {
            width: 110px; height: 130px;
            text-align: center;
            line-height: 130px;
            color: #9ca3af;
            font-size: 32px;
            font-weight: bold;
            background: #f3f4f6;
        }

        .identity-info { padding-left: 20px; }
        .student-name { font-size: 24px; font-weight: bold; color: #111827; }
        .adm-no { font-size: 13px; color: #2563eb; font-weight: bold; margin-top: 2px; }
        .badge-row { margin-top: 10px; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 6px;
        }
        .b-active { background: #d1fae5; color: #065f46; }
        .b-graduated { background: #dbeafe; color: #1e40af; }
        .b-withdrawn { background: #fee2e2; color: #991b1b; }
        .b-transferred { background: #fef3c7; color: #92400e; }
        .b-provisional { background: #fef3c7; color: #92400e; }
        .b-confirmed { background: #d1fae5; color: #065f46; }
        .b-class { background: #ede9fe; color: #5b21b6; }

        /* Section blocks */
        .section { margin-bottom: 18px; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2563eb;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 5px 8px; font-size: 11px; vertical-align: top; width: 50%; }
        .info-grid .label { color: #6b7280; font-weight: bold; display: block; font-size: 9px; text-transform: uppercase; }
        .info-grid .value { color: #111827; }

        .medical-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 11px;
            color: #7f1d1d;
        }

        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table>
            <tr>
                @if ($school->logo ?? false)
                    <td class="logo-cell"><img src="{{ $logoPath }}" alt="logo"></td>
                @endif
                <td>
                    <div class="school-name">{{ $school->name }}</div>
                    <div class="school-meta">
                        @if ($school->address){{ $school->address }} · @endif
                        @if ($school->phone)Tel: {{ $school->phone }}@endif
                    </div>
                </td>
                <td class="doc-tag">Student Profile</td>
            </tr>
        </table>
    </div>

    {{-- Identity band --}}
    <table class="identity">
        <tr>
            <td class="photo-cell">
                <div class="photo-frame">
                    @if ($photoPath)
                        <img src="{{ $photoPath }}" alt="photo">
                    @else
                        <div class="photo-placeholder">{{ strtoupper(substr($student->name, 0, 1)) }}</div>
                    @endif
                </div>
            </td>
            <td class="identity-info">
                <div class="student-name">{{ $student->name }}</div>
                <div class="adm-no">{{ $student->admission_no }}</div>
                <div class="badge-row">
                    @if ($student->schoolClass)
                        <span class="badge b-class">{{ $student->schoolClass->name }}@if ($student->section) — {{ $student->section->name }}@endif</span>
                    @endif
                    <span class="badge b-{{ $student->status }}">{{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}</span>
                    <span class="badge b-{{ $student->enrolment_status }}">{{ \App\Models\Student::ENROLMENT_STATUSES[$student->enrolment_status] ?? $student->enrolment_status }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Personal --}}
    <div class="section">
        <div class="section-title">Personal Information</div>
        <table class="info-grid">
            <tr>
                <td><span class="label">Date of Birth</span><span class="value">{{ $student->date_of_birth?->format('j F Y') ?? '—' }}</span></td>
                <td><span class="label">Age</span><span class="value">{{ $student->age !== null ? $student->age . ' years' : '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Gender</span><span class="value">{{ \App\Models\Student::GENDERS[$student->gender] ?? '—' }}</span></td>
                <td><span class="label">Admission Date</span><span class="value">{{ $student->admission_date?->format('j F Y') ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Learner ID (LIN)</span><span class="value">{{ $student->lin ?? '—' }}</span></td>
                <td><span class="label">National ID (NIN)</span><span class="value">{{ $student->nin ?? '—' }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Contact --}}
    <div class="section">
        <div class="section-title">Contact</div>
        <table class="info-grid">
            <tr>
                <td><span class="label">Phone</span><span class="value">{{ $student->phone ?? '—' }}</span></td>
                <td><span class="label">Email</span><span class="value">{{ $student->email ?? '—' }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Address</span><span class="value">{{ $student->address ?? '—' }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Guardian --}}
    @if ($student->guardian)
    <div class="section">
        <div class="section-title">Guardian / Parent</div>
        <table class="info-grid">
            <tr>
                <td><span class="label">Name</span><span class="value">{{ $student->guardian->name }}</span></td>
                <td><span class="label">Relationship</span><span class="value">{{ ucfirst($student->guardian->relationship ?? '—') }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Phone</span><span class="value">{{ $student->guardian->phone ?? '—' }}</span></td>
                <td><span class="label">Email</span><span class="value">{{ $student->guardian->email ?? '—' }}</span></td>
            </tr>
        </table>
    </div>
    @endif

    {{-- Enrolment --}}
    <div class="section">
        <div class="section-title">Enrolment</div>
        <table class="info-grid">
            <tr>
                <td><span class="label">Status</span><span class="value">{{ \App\Models\Student::ENROLMENT_STATUSES[$student->enrolment_status] ?? $student->enrolment_status }}</span></td>
                <td><span class="label">Confirmed On</span><span class="value">{{ $student->confirmed_at?->format('j F Y') ?? 'Not yet confirmed' }}</span></td>
            </tr>
            @if ($student->confirmed_via)
            <tr>
                <td colspan="2"><span class="label">Confirmed Via</span><span class="value">{{ $student->confirmed_via === 'payment' ? 'First fee payment' : 'Registrar confirmation' }}</span></td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Medical --}}
    @if ($student->medical_notes)
    <div class="section">
        <div class="section-title">Medical Notes</div>
        <div class="medical-box">{{ $student->medical_notes }}</div>
    </div>
    @endif

    <div class="footer">
        {{ $school->name }} · Generated {{ now()->format('j F Y, g:i a') }} · Confidential student record
    </div>

</body>
</html>
