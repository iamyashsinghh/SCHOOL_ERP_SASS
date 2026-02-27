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
                            @php
                                $i = 0;
                                $totalSlots = count($timeSlots);
                            @endphp

                            @while ($i < $totalSlots)
                                @php
                                    $slot = $timeSlots[$i];
                                    $sessionsInSlot = collect($dayData['sessions'])
                                        ->filter(function ($s) use ($slot) {
                                            return $s['start_time'] < $slot['end'] && $s['end_time'] > $slot['start'];
                                        })
                                        ->values();

                                    if ($sessionsInSlot->isNotEmpty()) {
                                        $firstSession = $sessionsInSlot->first();
                                        $colspan = 0;
                                        for ($j = $i; $j < $totalSlots; $j++) {
                                            if (
                                                $firstSession['start_time'] < $timeSlots[$j]['end'] &&
                                                $firstSession['end_time'] > $timeSlots[$j]['start']
                                            ) {
                                                $colspan++;
                                            } else {
                                                break;
                                            }
                                        }
                                        $i += $colspan;
                                    }
                                @endphp

                                @if ($sessionsInSlot->isNotEmpty())
                                    <td colspan="{{ $colspan }}" class="session">
                                        @foreach ($sessionsInSlot as $s)
                                            <div class="session-list">
                                                <strong>{{ $s['subject'] }}</strong><br>
                                                {{ $s['batch'] }}<br>
                                                <span style="font-size: 0.7rem;">{{ $s['room'] }}</span>
                                            </div>
                                            @if (!$loop->last)
                                                <hr>
                                            @endif
                                        @endforeach
                                    </td>
                                @else
                                    <td class="free-slot">Free</td>
                                    @php $i++; @endphp
                                @endif
                            @endwhile

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</x-print.layout>
