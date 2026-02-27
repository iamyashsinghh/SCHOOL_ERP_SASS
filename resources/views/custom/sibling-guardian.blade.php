<x-print.layout type="full-page">
    <x-print.wrapper>
        <h1 class="heading text-center">Sibling Guardian List</h1>

        <table class="mt-4 table">
            <thead>
                <tr>
                    <th>
                        #
                    </th>
                    <th>
                        <a
                            href="{{ route('custom.sibling-guardian', ['order_by' => 'father_name', 'sort_by' => $sortBy == 'asc' ? 'desc' : 'asc']) }}">
                            Father Name
                        </a>
                    </th>
                    <th>
                        <a
                            href="{{ route('custom.sibling-guardian', ['order_by' => 'mother_name', 'sort_by' => $sortBy == 'asc' ? 'desc' : 'asc']) }}">
                            Mother Name
                        </a>
                    </th>
                    <th>
                        <a
                            href="{{ route('custom.sibling-guardian', ['order_by' => 'students_count', 'sort_by' => $sortBy == 'asc' ? 'desc' : 'asc']) }}">
                            Student Count
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($uniqueParents as $parent)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $parent->father_name }}</td>
                        <td>{{ $parent->mother_name }}</td>
                        <td>{{ $parent->students_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-print.wrapper>
</x-print.layout>
