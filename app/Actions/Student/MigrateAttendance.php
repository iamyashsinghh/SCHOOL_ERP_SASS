<?php

namespace App\Actions\Student;

use App\Models\Student\Attendance;
use Illuminate\Support\Arr;

class MigrateAttendance
{
    public function execute(array $params = []): void
    {
        $studentUuid = Arr::get($params, 'student');
        $studentId = Arr::get($params, 'student_id');
        $previousBatchId = Arr::get($params, 'previous_batch_id');
        $studentBatchId = Arr::get($params, 'batch_id');
        $startDate = Arr::get($params, 'start_date');
        $endDate = Arr::get($params, 'end_date');

        $previousAttendances = Attendance::query()
            ->whereBatchId($previousBatchId)
            ->whereNull('subject_id')
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        $newAttendances = Attendance::query()
            ->whereBatchId($studentBatchId)
            ->whereNull('subject_id')
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        foreach ($previousAttendances as $attendance) {
            $values = collect($attendance->values);
            $modified = false;
            $movedCode = null;

            $values = $values->map(function ($entry) use ($studentUuid, &$modified, &$movedCode) {
                if (in_array($studentUuid, $entry['uuids'])) {
                    $entry['uuids'] = array_values(array_diff($entry['uuids'], [$studentUuid]));
                    $modified = true;
                    $movedCode = $entry['code'];
                }

                return $entry;
            });

            if ($modified) {
                $attendance->values = $values;
                $attendance->save();

                $targetAttendance = $newAttendances->firstWhere('date', $attendance->date);

                if ($targetAttendance) {
                    $newValues = collect($targetAttendance->values);

                    $updated = false;
                    $newValues = $newValues->map(function ($entry) use ($studentUuid, $movedCode, &$updated) {
                        if ($entry['code'] === $movedCode) {
                            if (! in_array($studentUuid, $entry['uuids'])) {
                                $entry['uuids'][] = $studentUuid;
                                $updated = true;
                            }
                        }

                        return $entry;
                    });

                    // If code not present, add new entry
                    if (! $updated) {
                        $newValues->push([
                            'code' => $movedCode,
                            'uuids' => [$studentUuid],
                        ]);
                    }

                    $targetAttendance->values = $newValues;
                    $targetAttendance->save();
                } else {
                    Attendance::create([
                        'date' => $attendance->date,
                        'batch_id' => $studentBatchId,
                        'values' => [[
                            'code' => $movedCode,
                            'uuids' => [$studentUuid],
                        ]],
                        'is_default' => true,
                        'session' => $attendance->session,
                    ]);
                }
            }
        }
    }
}
