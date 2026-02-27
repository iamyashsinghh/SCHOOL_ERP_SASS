<x-print.layout>
    <x-print.wrapper>
        <table class="mt-2" width="100%" border="0" cellspacing="4" cellpadding="0">
            <td colspan="2">
                <h2 class="heading text-center">
                    {{ trans('student.fee_refund.fee_refund') }}
                    @if ($transaction->cancelled_at->value)
                        <span style="color: red;">({{ trans('general.cancelled') }})</span>
                    @endif
                    @if ($transaction->rejected_at->value)
                        <span style="color: orange;">({{ trans('general.rejected') }})</span>
                    @endif
                </h2>
                <p class="text-center">{{ $student->batch->course->division?->program?->name }}
                    {{ $student->period->name }}
                </p>
            </td>
            <tr>
                <td width="50%" valign="top">
                    <div class="sub-heading-left">{{ trans('finance.transaction.props.code_number') }}:
                        {{ $transaction->code_number }}</div>
                </td>
                <td width="50%" valign="top">
                    <div class="sub-heading text-right">{{ trans('finance.transaction.props.date') }}:
                        {{ $transaction->date->formatted }}</div>
                </td>
            </tr>
        </table>
        <table class="mt-2 table" width="100%" border="0" cellspacing="4" cellpadding="0">
            <tr>
                <th>{{ trans('student.props.name') }}</th>
                <td class="text-right">{{ $student->name }}</td>
                <th>{{ trans('student.admission.props.code_number') }}</th>
                <td class="text-right">{{ $student->code_number }}</td>
            </tr>
            <tr>
                <th>{{ trans('contact.props.father_name') }}</th>
                <td class="text-right">
                    @auth
                        {{ $student->father_name }}
                    @else
                        {{ Str::alternateMask($student->father_name, 'x') }}
                    @endauth
                </td>
                <th>{{ trans('contact.props.contact_number') }}</th>
                <td class="text-right">
                    @auth
                        {{ $student->contact_number }}
                    @else
                        {{ Str::alternateMask($student->contact_number, 'x') }}
                    @endauth
                </td>
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
                    <th>{{ trans('finance.fee_head.fee_head') }}</th>
                    <th class="text-right">{{ trans('student.fee_refund.props.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($feeRefund->records as $record)
                    <tr>
                        <td>{{ $record->head->name }}</td>
                        <td class="text-right">{{ $record->amount->formatted }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>{{ trans('general.total') }}
                        {{ \App\Helpers\CurrencyConverter::toWord($feeRefund->total->value) }}</th>
                    <th class="text-right">

                        {{ $feeRefund->total->formatted }}
                    </th>
                </tr>
            </tfoot>
        </table>

        <table class="mt-4" width="100%" border="0">
            <tr>
                <td>
                    @foreach ($transaction->payments as $payment)
                        <div>
                            <strong>{{ trans('finance.payment_method.payment_method') }}</strong>:
                            {{ $payment->method->name }}
                            {{ $payment->amount->formatted }}
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
                <td class="text-right">
                    @if ($transaction->user_id)
                        <div class="font-90pc mt-1">
                            {{ trans('user.user') }}:
                            {{ $transaction->user->name }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        @if ($transaction->cancelled_at->value && $transaction->cancellation_remarks)
            <div class="mt-4">
                <p style="color: red;">{{ trans('finance.transaction.props.cancellation_remarks') }}:
                    {{ $transaction->cancellation_remarks }}</p>
            </div>
        @endif

        @if ($transaction->rejected_at->value && $transaction->rejection_remarks)
            <div class="mt-4">
                <p style="color: red;">{{ trans('finance.transaction.props.rejection_remarks') }}:
                    {{ $transaction->rejection_remarks }}</p>
            </div>
        @endif

        @if ($transaction->is_online)
            <div class="mt-4 text-center">
                <p>{{ trans('finance.online_receipt_info') }}</p>
            </div>
        @else
            <div class="mt-4 text-right">
                <h2>{{ trans('student.fee.authorized_signatory') }}</h2>
            </div>
        @endif
    </x-print.wrapper>
</x-print.layout>
