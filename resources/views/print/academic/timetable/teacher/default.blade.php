<x-print.layout type="full-page">

    <style>
        .timetable {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
        }

        .timetable th,
        .timetable td {
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
        }

        .timetable th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }

        .session-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .session {
            background: #e9f7ef;
            border: 1px solid #b2dfdb;
            padding: 5px;
            border-radius: 6px;
            font-size: 0.85rem;
            line-height: 1.2;
        }

        .session strong {
            display: block;
            font-size: 0.9rem;
            color: #2c3e50;
        }

        /* Responsive: smaller text & scrollable table on mobile */
        @media (max-width: 768px) {
            .timetable {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .timetable th,
            .timetable td {
                min-width: 150px;
            }
        }
    </style>

    @foreach ($data as $item)
        @php
            $employee = $item['employee'];
            $timetable = $item['timetable'];
            $timeSlots = $item['timeSlots'];
        @endphp
        <div style="page-break-after: always;">

            @includeFirst([config('config.print.custom_path') . 'header', 'print.header'])

            <h2 class="heading">
                {{ trans('academic.timetable.teacher_timetable') }}
            </h2>

            <div class="mt-4 sub-heading">
                {{ $employee->name . ' (' . $employee->code_number . ')' }}
            </div>

            <div class="mt-1 sub-heading">
                {{ $employee->designation_name }}
            </div>

            <table class="timetable mt-4">
                <thead>
                    <tr>
                        <th>Day</th>
                        @foreach ($timeSlots as $slot)
                            <th>
                                {{ \Carbon\Carbon::parse($slot['start'])->format('h:i A') }}<br>
                                to {{ \Carbon\Carbon::parse($slot['end'])->format('h:i A') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timetable as $dayData)
                        <tr>
                            <td><strong>{{ $dayData['day'] }}</strong></td>
                            @foreach ($timeSlots as $slot)
                                @php
                                    $sessions = collect($dayData['sessions'])->filter(function ($s) use ($slot) {
                                        return $s['start_time'] === $slot['start'] && $s['end_time'] === $slot['end'];
                                    });
                                @endphp

                                <td>
                                    @if ($sessions->count())
                                        <div class="session-list">
                                            @foreach ($sessions as $session)
                                                <div class="session">
                                                    <strong>{{ $session['subject'] }}</strong>
                                                    <div>{{ $session['batch'] }}</div>
                                                    <div>{{ $session['room'] }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</x-print.layout>
