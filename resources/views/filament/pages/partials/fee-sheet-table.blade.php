{{-- One group of classes on the Fees Structure sheet: its items, a column per residency, a total. --}}
<section class="fs-block">
    <h3 class="fs-block-title">{{ $group['classes'] }}</h3>

    <table class="fs-table">
        <thead>
            <tr>
                <th>Item</th>
                @foreach ($columns as $heading)
                    <th class="fs-num">{{ $heading }} <span>(UGX)</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($group['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    @foreach ($columns as $column => $heading)
                        <td class="fs-num">{{ $money($row['amounts'][$column] ?? null) ?? '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ $totalLabel }}</td>
                @foreach ($columns as $column => $heading)
                    <td class="fs-num">{{ $money($group['totals'][$column] ?? 0) }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</section>
