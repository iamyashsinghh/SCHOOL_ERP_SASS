<x-print.layout type="full-page">

    <style>
        * {
            font-size: .9rem;
        }

        .timetable {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
        }

        .timetable th,
        .timetable td {
            border: 1px solid #ddd;
            padding: 4px;
            vertical-align: top;
        }

        .timetable th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
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
            font-size: 0.75rem;
            line-height: 1.2;
        }

        .free-slot {
            background: #fdfdfd;
            color: #999;
            font-size: 0.7rem;
            text-align: center;
            padding: 5px;
        }

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
            $groups = $item['groups'];
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

            @foreach ($groups as $classTimingId => $group)
                @php
                    $timeSlots = collect($group['days']->first()['class_timing_sessions'])->values();
                @endphp

                {{-- <h3 style="margin: 20px 0 8px; font-weight: bold;">
                    Timetable – Class Timing ID {{ $classTimingId }}
                </h3> --}}

                <table class="timetable mt-4">
                    <thead>
                        <tr>
                            <th>Day</th>
                            @foreach ($timeSlots as $slot)
                                <th>
                                    {{ $slot['start_time_formatted'] }}<br>
                                    {{ $slot['end_time_formatted'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['days'] as $day)
                            <tr>
                                <td><strong>{{ $day['day'] }}</strong></td>

                                @foreach ($timeSlots as $slot)
                                    @php
                                        $matchingSessions = collect($day['sessions'])->filter(function ($s) use (
                                            $slot,
                                        ) {
                                            return $s['start_time'] == $slot['start_time'] &&
                                                $s['end_time'] == $slot['end_time'];
                                        });
                                    @endphp

                                    @if ($matchingSessions->isNotEmpty())
                                        <td>
                                            <div class="session-list">
                                                @foreach ($matchingSessions as $session)
                                                    <div class="session">
                                                        <strong>{{ $session['subject'] }}</strong><br>
                                                        {{ $session['batch'] }}<br>
                                                        {{ $session['room'] ?? '' }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    @else
                                        <td>
                                            <div class="free-slot">FREE</div>
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @endforeach

</x-print.layout>
