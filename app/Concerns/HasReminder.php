<?php

namespace App\Concerns;

use App\Models\Employee\Employee;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait HasReminder
{
    // public function reminders(): MorphMany
    // {
    //     return $this->morphMany(Reminder::class, 'remindable');
    // }

    public function reminder(): MorphOne
    {
        return $this->morphOne(Reminder::class, 'remindable');
    }

    private function getEmployees(array $users): Collection
    {
        return Employee::query()
            ->select('employees.id', 'contacts.id as contact_id', 'contacts.user_id')
            ->join('contacts', 'employees.contact_id', '=', 'contacts.id')
            ->whereNotNull('contacts.user_id')
            ->whereIn('employees.uuid', $users)
            ->get();
    }

    public function addReminder(Request $request): ?Reminder
    {
        if (! $request->boolean('has_reminder')) {
            return null;
        }

        if (! $request->input('reminder.date')) {
            return null;
        }

        $employees = $this->getEmployees($request->input('reminder.users'));

        $reminder = $this->reminder()->create([
            'title' => method_exists($this, 'getReminderTitle') ? $this->getReminderTitle() : class_basename($this),
            'date' => $request->input('reminder.date'),
            'notify_before' => $request->input('reminder.notify_before'),
            'note' => $request->input('reminder.note'),
            'user_id' => auth()->id(),
            'meta' => [
                'sub_title' => method_exists($this, 'getReminderSubTitle') ? $this->getReminderSubTitle() : null,
            ],
        ]);

        foreach ($employees as $employee) {
            $reminder->users()->attach($employee->user_id);
        }

        return $reminder;
    }

    public function updateReminder(Request $request): void
    {
        $reminder = $this->reminder;

        if (! $reminder) {
            $this->addReminder($request);

            return;
        }

        if (! $request->input('reminder.date')) {
            $this->deleteReminder();

            return;
        }

        $reminder->title = method_exists($this, 'getReminderTitle') ? $this->getReminderTitle() : class_basename($this);
        $reminder->note = $request->input('reminder.note');
        $reminder->date = $request->input('reminder.date');
        $reminder->notify_before = $request->input('reminder.notify_before');
        $reminder->setMeta([
            'sub_title' => method_exists($this, 'getReminderSubTitle') ? $this->getReminderSubTitle() : null,
        ]);
        $reminder->save();

        $employees = $this->getEmployees($request->input('reminder.users'));

        $reminder->users()->sync($employees->pluck('user_id'));
    }

    public function deleteReminder(): void
    {
        $reminder = $this->reminder;

        if (! $reminder) {
            return;
        }

        $reminder->delete();
    }
}
