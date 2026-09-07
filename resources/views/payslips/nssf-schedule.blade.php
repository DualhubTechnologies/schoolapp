<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>NSSF Contribution Schedule - {{ $period->period_label }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #333; padding: 25px; }

        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 16px; color: #1F4E78; margin-bottom: 2px; }
        .header h2 { font-size: 13px; color: #333; margin-bottom: 15px; }

        .title-bar { background: #1F4E78; color: white; text-align: center; padding: 8px; font-size: 13px; font-weight: bold; margin-bottom: 15px; }

        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 3px 8px; }
        .info-label { font-weight: bold; color: #555; width: 200px; }

        .schedule-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10px; }
        .schedule-table th { background: #1F4E78; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        .schedule-table th.amount { text-align: right; }
        .schedule-table td { padding: 5px 8px; border-bottom: 1px solid #DDD; }
        .schedule-table td.amount { text-align: right; font-family: monospace; }
        .schedule-table td.center { text-align: center; }
        .schedule-table tr:nth-child(even) { background: #F9F9F9; }
        .schedule-table .total-row { background: #E8EEF4; font-weight: bold; }
        .schedule-table .total-row td { border-top: 2px solid #1F4E78; padding: 8px; }
        .schedule-table .grand-total { background: #1F4E78; color: white; font-weight: bold; font-size: 11px; }
        .schedule-table .grand-total td { padding: 8px; border: none; }

        .summary-box { background: #F0F5FA; border: 1px solid #1F4E78; padding: 12px 15px; margin: 15px 0; }
        .summary-box h3 { color: #1F4E78; font-size: 12px; margin-bottom: 8px; }
        .summary-grid { width: 100%; }
        .summary-grid td { padding: 3px 8px; }
        .summary-grid .label { width: 250px; }
        .summary-grid .value { font-weight: bold; font-family: monospace; text-align: right; width: 200px; }

        .declaration { margin-top: 20px; font-size: 10px; line-height: 1.6; }
        .declaration p { margin-bottom: 8px; }

        .signature-grid { width: 100%; margin-top: 35px; }
        .signature-grid td { width: 50%; padding-top: 45px; }
        .signature-line { border-top: 1px solid #333; display: inline-block; width: 200px; margin-bottom: 4px; }
        .sig-label { font-size: 10px; color: #555; }

        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #DDD; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $school->name }}</h1>
        <p>{{ $school->address }}{{ $school->city ? ', ' . $school->city : '' }}</p>
    </div>

    <div class="title-bar">NATIONAL SOCIAL SECURITY FUND — MONTHLY CONTRIBUTION SCHEDULE</div>

    <table class="info-table">
        <tr>
            <td class="info-label">Employer Name:</td>
            <td>{{ $school->name }}</td>
            <td class="info-label">NSSF Employer No:</td>
            <td>{{ $school->nssf_employer_number ?? 'NOT SET' }}</td>
        </tr>
        <tr>
            <td class="info-label">TIN Number:</td>
            <td>{{ $school->tin_number ?? 'NOT SET' }}</td>
            <td class="info-label">Contribution Period:</td>
            <td><strong>{{ $period->period_label }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Address:</td>
            <td>{{ $school->address }}{{ $school->city ? ', ' . $school->city : '' }}</td>
            <td class="info-label">Number of Employees:</td>
            <td><strong>{{ $entries->count() }}</strong></td>
        </tr>
    </table>

    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 30px;">No.</th>
                <th>Employee Name</th>
                <th>NSSF No.</th>
                <th class="amount">Gross Salary ({{ $school->currency }})</th>
                <th class="amount">Employee 5% ({{ $school->currency }})</th>
                <th class="amount">Employer 10% ({{ $school->currency }})</th>
                <th class="amount">Total 15% ({{ $school->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalGross = 0;
                $totalEmployee = 0;
                $totalEmployer = 0;
                $totalContribution = 0;
            @endphp
            @foreach($entries as $index => $entry)
            @php
                $contribution = $entry->nssf_employee + $entry->nssf_employer;
                $totalGross += $entry->gross_pay;
                $totalEmployee += $entry->nssf_employee;
                $totalEmployer += $entry->nssf_employer;
                $totalContribution += $contribution;
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $entry->staff->name }}</td>
                <td>{{ $entry->staff->nssf_number ?? '—' }}</td>
                <td class="amount">{{ number_format($entry->gross_pay, 0) }}</td>
                <td class="amount">{{ number_format($entry->nssf_employee, 0) }}</td>
                <td class="amount">{{ number_format($entry->nssf_employer, 0) }}</td>
                <td class="amount">{{ number_format($contribution, 0) }}</td>
            </tr>
            @endforeach
            <tr class="grand-total">
                <td colspan="3">GRAND TOTAL</td>
                <td class="amount">{{ number_format($totalGross, 0) }}</td>
                <td class="amount">{{ number_format($totalEmployee, 0) }}</td>
                <td class="amount">{{ number_format($totalEmployer, 0) }}</td>
                <td class="amount">{{ number_format($totalContribution, 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary-box">
        <h3>CONTRIBUTION SUMMARY</h3>
        <table class="summary-grid">
            <tr>
                <td class="label">Total Employee Contributions (5%):</td>
                <td class="value">{{ $school->currency }} {{ number_format($totalEmployee, 0) }}</td>
            </tr>
            <tr>
                <td class="label">Total Employer Contributions (10%):</td>
                <td class="value">{{ $school->currency }} {{ number_format($totalEmployer, 0) }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Total Amount Payable to NSSF (15%):</strong></td>
                <td class="value"><strong>{{ $school->currency }} {{ number_format($totalContribution, 0) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="declaration">
        <p>I hereby certify that the above information is correct and that the contributions shown have been computed
        in accordance with the provisions of the National Social Security Fund Act.</p>
    </div>

    <table class="signature-grid">
        <tr>
            <td>
                <div class="signature-line"></div><br>
                <span class="sig-label">Authorized Signatory</span><br>
                <span class="sig-label">Name: ________________________</span><br>
                <span class="sig-label">Title: ________________________</span><br>
                <span class="sig-label">Date: ________________________</span>
            </td>
            <td>
                <div class="signature-line"></div><br>
                <span class="sig-label">Official Stamp</span>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d/m/Y H:i') }} | {{ $school->name }} | {{ $period->period_label }}
    </div>

</body>
</html>