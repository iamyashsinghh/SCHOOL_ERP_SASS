<x-print.layout type="full-page">

    @foreach ($exportedTimetables as $exportedTimetable)
        <div style="page-break-after: always;">
            @php
                $timetable = Arr::get($exportedTimetable, 'timetable');
                $batch = Arr::get($exportedTimetable, 'batch');
                $days = Arr::get($exportedTimetable, 'days');
            @endphp
            @includeFirst([config('config.print.custom_path') . 'header', 'print.header'])

            <h2 class="heading">
                {{ trans('academic.timetable.timetable') }}
            </h2>

            <div class="sub-heading mt-4">
                {{ $batch->course->name . ' ' . $batch->name }}
            </div>

            <div style="display: flex; justify-content: space-between;">
                <p>{{ trans('academic.timetable.props.effective_date') }}: {{ $timetable->effective_date->formatted }}
                </p>
                @if ($timetable->room_name)
                    <p>{{ trans('asset.building.room.room') }}: {{ $timetable->room_name }}</p>
                @endif
            </div>

            <table class="cellpadding mt-4 table" width="100%">
                @if ($timetable->has_same_class_timing)
                    <thead>
                        <tr>
                            <th>
                                {{ trans('academic.timetable.props.day') }}
                            </th>
                            @foreach ($days[0]['sessions'] as $session)
                                <th>
                                    {{ Arr::get($session, 'name') }}

                                    <div class="font-90pc">
                                        {{ Arr::get($session, 'start_time')?->formatted }} -
                                        {{ Arr::get($session, 'end_time')?->formatted }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                @foreach ($days as $day)
                    <tr>
                        <td>
                            {{ Arr::get($day, 'day.label') }}
                            @if (Arr::get($day, 'is_holiday'))
                                <div class="font-90pc">
                                    ({{ trans('academic.timetable.props.holiday') }})
                                </div>
                            @else
                                <div class="font-90pc">
                                    {{ Arr::get($day, 'start_time')?->formatted }} -
                                    {{ Arr::get($day, 'end_time')?->formatted }}
                                </div>
                            @endif
                        </td>
                        @foreach (Arr::get($day, 'sessions') as $session)
                            <td>
                                @foreach (Arr::get($session, 'allotments', []) as $allotment)
                                    {{ Arr::get($allotment, 'subject.name', '-') }}
                                    @if (Arr::get($allotment, 'room'))
                                        <div class="font-90pc">
                                            {{ Arr::get($allotment, 'room') }}
                                        </div>
                                    @endif
                                    @if (Arr::get($allotment, 'employee'))
                                        <div class="font-90pc">
                                            {{ Arr::get($allotment, 'employee') }}
                                        </div>
                                    @endif
                                @endforeach
                            </td>
                        @endforeach
                        @for ($i = Arr::get($day, 'filler_session'); $i > 0; $i--)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</x-print.layout>
