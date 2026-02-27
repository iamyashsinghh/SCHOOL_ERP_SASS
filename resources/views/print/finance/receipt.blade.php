<x-print.layout type="centered">
    @includeFirst([config('config.print.custom_path') . 'header', 'print.header'])

    <h2 class="heading">{{ Arr::get($receipt, 'type.label') }}</h2>

    <table width="100%">
        <tr>
            <td><strong>{{ trans('finance.transaction.props.code_number_short') }}
                    {{ Arr::get($receipt, 'code_number') }}</strong></td>
            <td class="text-right"><strong>{{ trans('finance.transaction.props.date') }}
                    {{ Arr::get($receipt, 'date.formatted') }}</strong></td>
        </tr>
    </table>

    @if (Arr::get($receipt, 'cancelled_at.value') && Arr::get($receipt, 'cancellation_remarks'))
        <div class="mt-4">
            <p style="color: red;">{{ trans('finance.transaction.props.cancellation_remarks') }}:
                {{ Arr::get($receipt, 'cancellation_remarks') }}</p>
        </div>
    @endif

    @if (Arr::get($receipt, 'rejected_at.value') && Arr::get($receipt, 'rejection_remarks'))
        <div class="mt-4">
            <p style="color: red;">{{ trans('finance.transaction.props.rejection_remarks') }}:
                {{ Arr::get($receipt, 'rejection_remarks') }}</p>
        </div>
    @endif

    <table class="mt-4 table" style="table-layout: fixed;">
        <tr>
            <th style="width:25%;">{{ trans('general.detail') }}</th>
            <td>
                {{ Arr::get($receipt, 'detail.name') }} </br>
                <span class="font-90pc">{{ Arr::get($receipt, 'detail.contact_number') }}</span>
                {{ Arr::get($receipt, 'record.ledger.name') }}
            </td>
        </tr>
        <tr>
            <th style="width:25%;">{{ trans('finance.transaction.props.head') }}</th>
            <td>
                {{ Arr::get($receipt, 'record.ledger.name') }}
            </td>
        </tr>
        <tr>
            <th>{{ trans('finance.transaction.props.amount') }}</th>
            <td>{{ Arr::get($receipt, 'amount.formatted') }}
                {{ App\Helpers\CurrencyConverter::toWord(Arr::get($receipt, 'amount.value')) }}</td>
        </tr>
        <tr>
            <th>{{ trans('finance.transaction.props.description') }}</th>
            <td>{{ Arr::get($receipt, 'description') ?? Arr::get($receipt, 'remarks') }}</td>
        </tr>
        <tr>
            <th>{{ trans('finance.payment_method.payment_method') }}</th>
            <td>

                @foreach (Arr::get($receipt, 'payments', []) as $payment)
                    <div>
                        {{ Arr::get($payment, 'method_name') }}
                        {{ Arr::get($payment, 'amount.formatted') }}
                    </div>
                    <div class="font-90pc mt-1">
                        @if (Arr::get($payment, 'details.reference_number'))
                            {{ trans('finance.transaction.props.reference_number') }}:
                            {{ Arr::get($payment, 'details.reference_number') }}
                        @endif
                        @if (Arr::get($payment, 'details.instrument_number'))
                            {{ trans('finance.transaction.props.instrument_number') }}:
                            {{ Arr::get($payment, 'details.instrument_number') }}
                        @endif
                        @if (Arr::get($payment, 'details.card_provider'))
                            {{ trans('finance.transaction.props.card_provider') }}:
                            {{ Arr::get($payment, 'details.card_provider') }}
                        @endif
                        @if (Arr::get($payment, 'details.instrument_date'))
                            {{ trans('finance.transaction.props.instrument_date') }}:
                            {{ \Cal::date(Arr::get($payment, 'details.instrument_date'))->formatted }}
                        @endif
                        @if (Arr::get($payment, 'details.clearing_date'))
                            {{ trans('finance.transaction.props.clearing_date') }}:
                            {{ \Cal::date(Arr::get($payment, 'details.clearing_date'))->formatted }}
                        @endif
                        @if (Arr::get($payment, 'details.bank_detail'))
                            {{ trans('finance.transaction.props.bank_detail') }}:
                            {{ Arr::get($payment, 'details.bank_detail') }}
                        @endif
                        @if (Arr::get($payment, 'details.branch_detail'))
                            {{ trans('finance.transaction.props.branch_detail') }}:
                            {{ Arr::get($payment, 'details.branch_detail') }}
                        @endif
                    </div>
                @endforeach
            </td>
        </tr>
    </table>

    <div class="mt-4">
        <p class="font-80pc">{{ trans('user.user') }}: {{ Arr::get($receipt, 'user.profile.name') }}</p>
    </div>
    <div class="mt-8">
        <p class="text-right">{{ trans('print.authorized_signatory') }}</p>
    </div>
    <div class="mt-4">
        <p>{{ trans('general.printed_at') }}: {{ \Cal::dateTime(now())->formatted }}
        </p>
    </div>
</x-print.layout>
