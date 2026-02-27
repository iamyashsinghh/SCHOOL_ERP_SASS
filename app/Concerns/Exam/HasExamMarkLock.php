<?php

namespace App\Concerns\Exam;

use App\Models\Exam\Record;
use App\Models\Exam\Schedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait HasExamMarkLock
{
    public function getAutoLockDate(Schedule $schedule, ?Record $examRecord = null)
    {
        if (! config('config.exam.enable_auto_lock_marks')) {
            return null;
        }

        $autoLockMarksPeriod = config('config.exam.auto_lock_marks_period', 7);

        $examDate = $examRecord?->date?->value ?: $schedule->last_exam_date;

        $examDate = Carbon::parse($examDate);
        $autoLockDate = $examDate->addDays((int) $autoLockMarksPeriod);

        if ($examRecord?->getConfig('unlock_till')) {
            $unlockTill = Carbon::parse($examRecord->getConfig('unlock_till'))->addMinutes((int) config('config.exam.unlock_temporarily_period', 15));

            if ($unlockTill > now()) {
                return null;
            }
        }

        if ($schedule?->getConfig('unlock_till')) {
            $unlockTill = Carbon::parse($schedule->getConfig('unlock_till'))->addMinutes((int) config('config.exam.unlock_temporarily_period', 15));

            if ($unlockTill > now()) {
                return null;
            }
        }

        return $autoLockDate->toDateString();
    }

    public function isExamMarkLocked(?string $autoLockDate = null): bool
    {
        if (! config('config.exam.enable_auto_lock_marks')) {
            return false;
        }

        if (! $autoLockDate) {
            return false;
        }

        return Carbon::parse($autoLockDate)->isPast();
    }

    public function validateExamMarkLock(Schedule $schedule, ?Record $examRecord = null)
    {
        if (auth()->user()->is_default) {
            return;
        }

        $autoLockDate = $this->getAutoLockDate($schedule, $examRecord);

        $isLocked = $this->isExamMarkLocked($autoLockDate);

        if ($isLocked) {
            throw ValidationException::withMessages(['message' => trans('exam.schedule.could_not_alter_mark_for_expired_period')]);
        }
    }

    public function validateRemovalExamMark(Schedule $schedule)
    {
        if ($schedule->status == 'processed') {
            throw ValidationException::withMessages(['message' => trans('exam.schedule.cannot_alter_after_marksheet_processed')]);
        }
    }
}
