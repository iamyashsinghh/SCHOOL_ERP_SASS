<?php

namespace App\Actions\Approval;

use App\Enums\Approval\Status as ApprovalRequestStatus;
use App\Models\Tenant\Approval\Request as ApprovalRequest;
use App\Models\Tenant\Approval\Type;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\RequestRecord;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CreateRequest
{
    use FormatCodeNumber;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.approval.request_number_prefix');
        $numberSuffix = config('config.approval.request_number_suffix');
        $digit = config('config.approval.request_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) ApprovalRequest::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function execute(Request $request, Type $type, array $params = []): ApprovalRequest
    {
        $codeNumber = $this->codeNumber();

        $dueIn = $type->getConfig('due_in', null);

        $dueDate = null;
        if (! is_null($dueIn)) {
            $dueDate = now()->addDays((int) $dueIn)->toDateString();
        }

        $approvalRequest = ApprovalRequest::forceCreate([
            'type_id' => $type->id,
            'priority_id' => $type->priority_id,
            'due_date' => $dueDate,
            'number_format' => Arr::get($codeNumber, 'number_format'),
            'number' => Arr::get($codeNumber, 'number'),
            'code_number' => Arr::get($codeNumber, 'code_number'),
            'title' => Arr::get($params, 'title'),
            'model_type' => Arr::get($params, 'model_type'),
            'model_id' => Arr::get($params, 'model_id'),
            'request_user_id' => auth()->id(),
            'date' => today()->toDateString(),
            'status' => ApprovalRequestStatus::REQUESTED->value,
            'meta' => Arr::get($params, 'meta'),
        ]);

        $this->createRequestRecords($approvalRequest, $type);

        return $approvalRequest;
    }

    private function createRequestRecords(ApprovalRequest $approvalRequest, Type $type): void
    {
        $type->load('levels');

        foreach ($type->levels as $index => $level) {
            $employee = Employee::query()
                ->select('employees.id', 'contacts.user_id', 'contacts.email')
                ->join('contacts', 'contacts.id', '=', 'employees.contact_id')
                ->where('employees.id', $level->employee_id)
                ->first();

            if (! $employee) {
                continue;
            }

            $requestRecord = RequestRecord::firstOrCreate([
                'model_type' => 'ApprovalRequest',
                'model_id' => $approvalRequest->id,
                'user_id' => $employee->user_id,
            ]);

            if ($index == 0 && ! $requestRecord->received_at->value) {
                $requestRecord->received_at = now()->toDateTimeString();
                $requestRecord->processed_at = null;
            }

            $requestRecord->status = ApprovalRequestStatus::REQUESTED->value;
            $requestRecord->save();
        }
    }
}
