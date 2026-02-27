<x-print.layout type="full-page">
    <x-print.wrapper>

        <h2 class="heading text-center">{{ $startDate }} - {{ $endDate }}</h2>

        @foreach ($rows as $row)
            <h2 class="heading text-center">{{ Arr::get($row, 'name') }}</h2>

            <table class="table">
                <thead>
                    <tr>
                        @foreach (Arr::get($row, 'headers') as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (Arr::get($row, 'data') as $data)
                        <tr>
                            @foreach ($data as $item)
                                <td>{{ $item }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        @foreach (Arr::get($row, 'footer', []) as $footer)
                            <th>{{ $footer }}</th>
                        @endforeach
                        {{-- @if (count(Arr::get($row, 'footer')) < count(Arr::get($row, 'headers'))) --}}
                        <th colspan="100"></th>
                        {{-- @endif --}}
                    </tr>
                </tfoot>
            </table>
        @endforeach

    </x-print.wrapper>
</x-print.layout>
