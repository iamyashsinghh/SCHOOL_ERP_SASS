<?php

namespace App\Concerns;

use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Incharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait HasIncharge
{
    public function validateInput(Employee $employee, Model $model, ?Model $detail = null, ?string $uuid = null, array $params = [])
    {
        $modelType = $model->getMorphClass();
        $detailType = $detail ? $detail->getMorphClass() : null;

        $lastestRecord = Incharge::query()
            ->whereModelType($modelType)
            ->whereModelId($model->id)
            ->whereDetailType($detailType)
            ->whereDetailId($detail?->id)
            ->whereEmployeeId($employee->id)
            ->when($uuid, function ($q, $uuid) {
                $q->where('uuid', '!=', $uuid);
            })
            ->where('start_date', '>=', $this->start_date)
            ->orderBy('start_date', 'desc')
            ->first();

        if ($lastestRecord && empty($this->end_date)) {
            throw ValidationException::withMessages(['end_date' => trans('validation.required', ['attribute' => trans('employee.incharge.props.end_date')])]);
        }

        $type = Arr::get($params, 'type');

        $previousRecord = Incharge::query()
            ->whereModelType($modelType)
            ->whereModelId($model->id)
            ->whereDetailType($detailType)
            ->whereDetailId($detail?->id)
            ->whereEmployeeId($employee->id)
            ->when($uuid, function ($q, $uuid) {
                $q->where('uuid', '!=', $uuid);
            })
            ->when($type, function ($q, $type) {
                $q->where('meta->type', $type);
            })
            ->where('start_date', '<=', $this->start_date)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $this->start_date);
            })
            ->orderBy('start_date', 'desc')
            ->first();

        if ($previousRecord && empty($previousRecord->end_date->value)) {
            throw ValidationException::withMessages(['employee' => trans('employee.incharge.period_not_ended')]);
        }

        if ($previousRecord && $previousRecord->end_date->value) {
            throw ValidationException::withMessages(['employee' => trans('employee.incharge.overlapping_period')]);
        }
    }
}
