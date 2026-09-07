<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $entry->staff->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #333; padding: 30px; }

        .header { text-align: center; margin-bottom: 25px; border-bottom: 3px solid #1F4E78; padding-bottom: 15px; }
        .header h1 { font-size: 18px; color: #1F4E78; margin-bottom: 3px; }
        .header h2 { font-size: 14px; color: #555; font-weight: normal; }
        .header p { font-size: 11px; color: #777; }

        .payslip-title { background: #1F4E78; color: white; text-align: center; padding: 8px; font-size: 14px; font-weight: bold; margin-bottom: 20px; }

        .info-grid { width: 100%; margin-bottom: 20px; }
        .info-grid td { padding: 4px 8px; vertical-align: top; }
        .info-label { font-weight: bold; color: #555; width: 140px; }
        .info-value { color: #333; }

        .section-title { background: #E8EEF4; padding: 6px 10px; font-weight: bold; color: #1F4E78; margin: 15px 0 5px 0; font-size: 12px; }

        .breakdown-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .breakdown-table th { background: #F5F5F5; padding: 6px 10px; text-align: left; font-size: 11px; color: #555; border-bottom: 1px solid #DDD; }
        .breakdown-table td { padding: 6px 10px; border-bottom: 1px solid #EEE; }
        .breakdown-table .amount { text-align: right; font-family: monospace; font-size: 12px; }
        .breakdown-table .total-row { background: #F0F5FA; font-weight: bold; }
        .breakdown-table .total-row td { border-top: 2px solid #1F4E78; }

        .net-pay-box { background: #1F4E78; color: white; padding: 12px 20px; margin: 20px 0; text-align: right; }
        .net-pay-box .label { font-size: 14px; }
        .net-pay-box .amount { font-size: 22px; font-weight: bold; font-family: monospace; }

        .employer-note { background: #FFF8E1; border: 1px solid #FFE082; padding: 8px 12px; margin: 10px 0; font-size: 11px; color: #795548; }

        .footer { margin-top: 40px; border-top: 1px solid #DDD; padding-top: 15px; }
        .signature-grid { width: 100%; margin-top: 30px; }
        .signature-grid td { width: 33%; text-align: center; padding-top: 40px; }
        .signature-line { border-top: 1px solid #999; display: inline-block; width: 150px; margin-bottom: 5px; }

        .two-col { width: 100%; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 5px; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <h1>{{ $school->name }}</h1>
        <p>{{ $school->address }}{{ $school->city ? ', ' . $school->city : '' }}{{ $school->country ? ', ' . $school->country : '' }}</p>
        <p>{{ $school->phone ? 'Tel: ' . $school->phone . ' | ' : '' }}{{ $school->email }}</p>
    </div>

    <div class="payslip-title">PAYSLIP — {{ $period->period_label }}</div>

    {{-- STAFF INFO --}}
    <table class="info-grid">
        <tr>
            <td class="info-label">Employee Name:</td>
            <td class="info-value">{{ $entry->staff->name }}</td>
            <td class="info-label">Staff No:</td>
            <td class="info-value">{{ $entry->staff->staff_no }}</td>
        </tr>
        <tr>
            <td class="info-label">Position:</td>
            <td class="info-value">{{ $entry->staff->position }}</td>
            <td class="info-label">Department:</td>
            <td class="info-value">{{ $entry->staff->department ?? '—' }}</td>
        </tr>
        @if($bankDetail)
        <tr>
            <td class="info-label">Payment Method:</td>
            <td class="info-value">{{ ucfirst(str_replace('_', ' ', $bankDetail->payment_method)) }}</td>
            <td class="info-label">
                @if($bankDetail->payment_method === 'bank_transfer')
                    Bank / Account:
                @elseif($bankDetail->payment_method === 'mobile_money')
                    Provider / Number:
                @endif
            </td>
            <td class="info-value">
                @if($bankDetail->payment_method === 'bank_transfer')
                    {{ $bankDetail->bank_name }} / {{ $bankDetail->account_number }}
                @elseif($bankDetail->payment_method === 'mobile_money')
                    {{ $bankDetail->mobile_money_provider }} / {{ $bankDetail->mobile_money_number }}
                @else
                    Cash
                @endif
            </td>
        </tr>
        @endif
    </table>

    {{-- EARNINGS & DEDUCTIONS SIDE BY SIDE --}}
    <table class="two-col">
        <tr>
            {{-- EARNINGS --}}
            <td>
                <div class="section-title">EARNINGS</div>
                <table class="breakdown-table">
                    <tr><th>Description</th><th class="amount">Amount ({{ $school->currency }})</th></tr>
                    <tr>
                        <td>Base Salary</td>
                        <td class="amount">{{ number_format($entry->base_salary, 0) }}</td>
                    </tr>
                    @foreach($allowanceItems as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ number_format($item->amount, 0) }}</td>
                    </tr>
                    @endforeach
                    @foreach($arrearItems as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ number_format($item->amount, 0) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Gross Pay</td>
                        <td class="amount">{{ number_format($entry->gross_pay + $entry->arrears_amount, 0) }}</td>
                    </tr>
                </table>
            </td>

            {{-- DEDUCTIONS --}}
            <td>
                <div class="section-title">DEDUCTIONS</div>
                <table class="breakdown-table">
                    <tr><th>Description</th><th class="amount">Amount ({{ $school->currency }})</th></tr>
                    @foreach($statutoryItems as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ number_format($item->amount, 0) }}</td>
                    </tr>
                    @endforeach
                    @foreach($deductionItems as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="amount">{{ number_format($item->amount, 0) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="amount">{{ number_format($entry->total_statutory + $entry->total_deductions, 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- NET PAY --}}
    <div class="net-pay-box">
        <span class="label">NET PAY:</span>
        <span class="amount">{{ $school->currency }} {{ number_format($entry->net_pay, 0) }}</span>
    </div>

    {{-- EMPLOYER NSSF NOTE --}}
    <div class="employer-note">
        <strong>Employer's NSSF Contribution (10%):</strong> {{ $school->currency }} {{ number_format($entry->nssf_employer, 0) }}
        — This amount is paid by {{ $school->name }} to NSSF on behalf of the employee and is not deducted from the employee's salary.
    </div>

    {{-- SIGNATURES --}}
    <div class="footer">
        <table class="signature-grid">
            <tr>
                <td>
                    <div class="signature-line"></div><br>
                    <small>Prepared by</small>
                </td>
                <td>
                    <div class="signature-line"></div><br>
                    <small>Approved by</small>
                </td>
                <td>
                    <div class="signature-line"></div><br>
                    <small>Received by (Employee)</small>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>