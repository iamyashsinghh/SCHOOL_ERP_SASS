<x-print.layout>
    <x-print.wrapper>
        <table class="mt-2 table" width="100%" border="0" cellspacing="4" cellpadding="0">
            <tr>
                <th>{{ trans('student.props.name') }}</th>
                <td class="text-right">{{ $student->name }}</td>
                <th>{{ trans('student.admission.props.code_number') }}</th>
                <td class="text-right">{{ $student->code_number }}</td>
            </tr>
            <tr>
                <th>{{ trans('contact.props.father_name') }}</th>
                <td class="text-right">{{ $student->father_name }}</td>
                <th>{{ trans('contact.props.contact_number') }}</th>
                <td class="text-right">{{ $student->contact_number }}</td>
            </tr>
            <tr>
                <th>{{ trans('academic.course.course') }}</th>
                <td class="text-right">
                    {{ $student->course_name . ' ' . $student->batch_name }} <br />
                    <span class="font-90pc"></span>
                </td>
                <th>{{ trans('contact.props.birth_date') }}</th>
                <td class="text-right">{{ \Cal::date($student->birth_date)->formatted }}</td>
            </tr>
        </table>

        <table class="mt-2 table" width="100%" border="0" cellspacing="4" cellpadding="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ trans('finance.transaction.props.code_number') }}</th>
                    <th>{{ trans('finance.transaction.props.date') }}</th>
                    <th>{{ trans('finance.payment_method.payment_method') }}</th>
                    <th>{{ trans('general.detail') }}</th>
                    <th class="text-right">{{ trans('finance.transaction.props.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $transaction->code_number }}</td>
                        <td>{{ $transaction->date->formatted }}</td>
                        <td>
                            @foreach ($transaction->payments as $payment)
                                <div>
                                    {{ $payment->method->name }}
                                </div>
                                <div class="font-90pc mt-1">
                                    @if ($payment->getDetail('reference_number'))
                                        {{ trans('finance.transaction.props.reference_number') }}:
                                        {{ $payment->getDetail('reference_number') }}
                                    @endif
                                    @if ($payment->getDetail('card_provider'))
                                        {{ trans('finance.transaction.props.card_provider') }}:
                                        {{ $payment->getDetail('card_provider') }}
                                    @endif
                                    @if ($payment->getDetail('instrument_number'))
                                        {{ trans('finance.transaction.props.instrument_number') }}:
                                        {{ $payment->getDetail('instrument_number') }}
                                    @endif
                                    @if ($payment->getDetail('instrument_date'))
                                        {{ trans('finance.transaction.props.instrument_date') }}:
                                        {{ \Cal::date($payment->getDetail('instrument_date'))->formatted }}
                                    @endif
                                    @if ($payment->getDetail('clearing_date'))
                                        {{ trans('finance.transaction.props.clearing_date') }}:
                                        {{ \Cal::date($payment->getDetail('clearing_date'))->formatted }}
                                    @endif
                                    @if ($payment->getDetail('bank_detail'))
                                        {{ trans('finance.transaction.props.bank_detail') }}:
                                        {{ $payment->getDetail('bank_detail') }}
                                    @endif
                                    @if ($payment->getDetail('branch_detail'))
                                        {{ trans('finance.transaction.props.branch_detail') }}:
                                        {{ $payment->getDetail('branch_detail') }}
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td>
                            @foreach ($transaction->fee_payments as $feePayment)
                                <div class="font-90pc">
                                    <div>
                                        @if ($feePayment->fee_head_id)
                                            {{ $feePayment->head?->name }}
                                        @elseif ($feePayment->default_fee_head)
                                            {{ $feePayment->getDefaultFeeHeadName() }}
                                        @endif
                                        {{ $feePayment->amount->formatted }}
                                    </div>
                                </div>
                            @endforeach
                        </td>
                        <td class="text-right">{{ $transaction->amount->formatted }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">
                        <strong>{{ trans('finance.fee.total') }} - {{ $totalAmountInWords }} </strong>
                    </td>
                    <td colspan="3">
                        <div class="text-right"> {{ $transactions->sum('amount.value') }}
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </x-print.wrapper>
</x-print.layout>
