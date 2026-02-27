@if ($students->count())
    <h3 style="margin-top: 20px;">Duplicate Primary Guardians found for students ({{ $students->count() }})</h3>

    <table style="margin-top: 20px;">
        <tr>
            <th>Student</th>
            <th>Code Number</th>
            <th>Guardians</th>
        </tr>
        @foreach ($students as $student)
            <tr>
                <td>{{ Arr::get($student, 'name') }}</td>
                <td>{{ Arr::get($student, 'code_number') }}</td>
                <td>
                    <ul>
                        @foreach (Arr::get($student, 'guardians', []) as $guardian)
                            <li>
                                <a
                                    href="{{ route('custom.duplicate-primary-guardian', ['student' => Arr::get($student, 'uuid'), 'guardian' => Arr::get($guardian, 'uuid')]) }}">
                                    {{ Arr::get($guardian, 'name') }} {{ Arr::get($guardian, 'relation.label') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endforeach
    </table>
@endif
