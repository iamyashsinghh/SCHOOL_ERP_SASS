<x-message.layout :url="$url" :action-text="$actionText">
    <div class="flex items-center">
        <p class="text-primary text-4xl font-extrabold sm:text-5xl">
            @if (isset($type) && $type == 'success')
                <i class="fas fa-check-circle text-success h-12 w-12"></i>
            @else
                <i class="fas fa-circle-exclamation text-danger h-12 w-12"></i>
            @endif
        </p>
        <div class="sm:ml-6">
            <div class="sm:border-l sm:border-gray-600 sm:pl-6">
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 sm:text-2xl">{{ $message }}
                </h1>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-center gap-2">
        <a class="bg-primary rounded px-4 py-2 text-gray-200" href="{{ $studentUrl }}">
            {{ trans('global.go_to', ['attribute' => trans('student.student')]) }}
        </a>
        @if ($type == 'success')
            @isset($receiptUrl)
                <a class="bg-info rounded px-4 py-2 text-gray-200" href="{{ $receiptUrl }}" target="_blank">
                    {{ trans('global.print', ['attribute' => trans('finance.transaction.types.receipt')]) }}
                </a>
            @endisset
        @endif
    </div>
</x-message.layout>
