@if (count($notPaid))
    <h3 style="margin-top: 20px;">Not Paid ({{ count($notPaid) }})</h3>

    <table style="margin-top: 20px;">
        <tr>
            <th>UUID</th>
            <th>Name</th>
            <th>Team Name</th>
            <th>Period Name</th>
            <th>Admission Code</th>
            <th>Record Total</th>
            <th>Fee Total</th>
            <th>Difference</th>
            <th>Additional Charge</th>
            <th>Additional Discount</th>
        </tr>
        @foreach ($notPaid as $transaction)
            <tr>
                <td><a target="_blank"
                        href="{{ route('custom.fix-additional-fee-mismatch', $transaction['uuid']) }}">{{ $transaction['uuid'] }}</a>
                </td>
                <td>{{ $transaction['name'] }}</td>
                <td>{{ $transaction['team_name'] }}</td>
                <td>{{ $transaction['period_name'] }}</td>
                <td>{{ $transaction['admission_code'] }}</td>
                <td>{{ $transaction['record_total'] }}</td>
                <td>{{ $transaction['fee_total'] }}</td>
                <td>{{ $transaction['difference'] }}</td>
                <td>{{ $transaction['additional_charge'] }}</td>
                <td>{{ $transaction['additional_discount'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (count($paid))
    <h3 style="margin-top: 20px;">Paid ({{ count($paid) }})</h3>

    <table style="margin-top: 20px;">
        <tr>
            <th>UUID</th>
            <th>Name</th>
            <th>Team Name</th>
            <th>Period Name</th>
            <th>Admission Code</th>
            <th>RecordTotal</th>
            <th>Fee Total</th>
            <th>Difference</th>
            <th>Additional Charge</th>
            <th>Additional Discount</th>
        </tr>
        @foreach ($paid as $transaction)
            <tr>
                <td><a target="_blank"
                        href="{{ route('custom.fix-additional-fee-mismatch', $transaction['uuid']) }}">{{ $transaction['uuid'] }}</a>
                </td>
                <td>{{ $transaction['name'] }}</td>
                <td>{{ $transaction['team_name'] }}</td>
                <td>{{ $transaction['period_name'] }}</td>
                <td>{{ $transaction['admission_code'] }}</td>
                <td>{{ $transaction['record_total'] }}</td>
                <td>{{ $transaction['fee_total'] }}</td>
                <td>{{ $transaction['difference'] }}</td>
                <td>{{ $transaction['additional_charge'] }}</td>
                <td>{{ $transaction['additional_discount'] }}</td>
            </tr>
        @endforeach
    </table>
@endif
