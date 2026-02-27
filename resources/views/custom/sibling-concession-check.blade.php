<x-print.layout type="full-page">
    <x-print.wrapper>
        <h1 class="heading text-center">Sibling Concession Check
            @if ($feeConcession)
                ({{ $feeConcession?->name }})
            @endif
        </h1>

        <table class="mt-4 table">
            <thead>
                <tr>
                    <th>
                        #
                    </th>
                    <th>
                        Name
                    </th>
                    <th>
                        Admission Number
                    </th>
                    <th>
                        Father Name
                    </th>
                    <th>
                        Mother Name
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $student->name }}</td>
                        <td>{{ $student->code_number }}</td>
                        <td>{{ $student->father_name }}</td>
                        <td>{{ $student->mother_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4">
            <form action="{{ route('custom.sibling-concession-check') }}" method="get">
                <input type="text" name="concession" value="{{ request()->query('concession') }}">
                <button type="submit">Search</button>
            </form>
        </div>
    </x-print.wrapper>
</x-print.layout>
