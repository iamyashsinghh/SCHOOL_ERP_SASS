<?php

namespace App\Services\Student\Config;

use App\Enums\OptionType;
use App\Helpers\ListHelper;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceTypeService
{
    public function preRequisite(Request $request): array
    {
        $subTypes = [
            ['value' => 'present', 'label' => trans('student.attendance_type.sub_types.present')],
            ['value' => 'absent', 'label' => trans('student.attendance_type.sub_types.absent')],
        ];

        $colors = ListHelper::getListKey('colors');

        return compact('subTypes', 'colors');
    }

    public function create(Request $request): Option
    {
        \DB::beginTransaction();

        $attendanceType = Option::forceCreate($this->formatParams($request));

        \DB::commit();

        return $attendanceType;
    }

    private function formatParams(Request $request, ?Option $attendanceType = null): array
    {
        $formatted = [
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => OptionType::STUDENT_ATTENDANCE_TYPE->value,
            'description' => $request->description,
        ];

        $formatted['meta']['code'] = $request->code;
        $formatted['meta']['color'] = $request->color;
        $formatted['meta']['sub_type'] = $request->sub_type;

        if (! $attendanceType) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Option $attendanceType): void
    {
        \DB::beginTransaction();

        $attendanceType->forceFill($this->formatParams($request, $attendanceType))->save();

        \DB::commit();
    }

    public function deletable(Request $request, Option $attendanceType): void {}
}
