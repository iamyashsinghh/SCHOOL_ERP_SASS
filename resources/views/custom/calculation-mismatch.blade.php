<h3 style="margin-top: 20px;">Not Paid ({{ count($data) }})</h3>

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
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['uuid'] }}</td>
            <td>{{ $item['name'] }}</td>
            <td>{{ $item['team_name'] }}</td>
            <td>{{ $item['period_name'] }}</td>
            <td>{{ $item['admission_code'] }}</td>
            <td>{{ $item['record_total'] }}</td>
            <td>{{ $item['fee_total'] }}</td>
            <td>{{ $item['difference'] }}</td>
            <td>{{ $item['additional_charge'] }}</td>
            <td>{{ $item['additional_discount'] }}</td>
        </tr>
    @endforeach
</table>
