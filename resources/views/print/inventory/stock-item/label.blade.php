<x-print.layout type="centered">
    @foreach (collect($stockItemCopies)->chunk($labelPerPage) as $stockItemCopy)
        <div style="page-break-after: always;">
            @foreach ($stockItemCopy->chunk($column) as $stockItem)
                <div style="margin-top: 5px; display: flex; justify-content: space-between; gap: 5px;">
                    @foreach ($stockItem as $item)
                        <div>
                            <center><img style="width: 100px; height: 100px;" src="{{ $item['qr_code'] }}" alt="QR Code">
                            </center>
                            <div class="text-center">{{ Arr::get($item, 'code_number') }}</div>
                            <div class="text-center">{{ Arr::get($item, 'name') }}</div>
                            <div class="text-center">{{ Arr::get($item, 'category') }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach
</x-print.layout>
