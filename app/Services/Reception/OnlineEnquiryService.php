<?php

namespace App\Services\Reception;

use App\Actions\Config\SetTeamWiseModuleConfig;
use App\Actions\CreateContact;
use App\Enums\Gender;
use App\Enums\Reception\EnquiryNature;
use App\Enums\Reception\EnquiryStatus;
use App\Http\Resources\Academic\CourseForGuestResource;
use App\Http\Resources\Academic\PeriodForGuestResource;
use App\Http\Resources\Academic\ProgramForGuestResource;
use App\Http\Resources\TeamForGuestResource;
use App\Models\Academic\Batch;
use App\Models\Academic\Course;
use App\Models\Academic\Period;
use App\Models\Academic\Program;
use App\Models\Reception\Enquiry;
use App\Models\Team;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OnlineEnquiryService
{
    use FormatCodeNumber;

    public function setFinanceConfig(int $teamId, string $module = 'finance')
    {
        (new SetTeamWiseModuleConfig)->execute($teamId, $module);
    }

    private function codeNumber(?int $teamId = null): array
    {
        $numberPrefix = config('config.reception.enquiry_number_prefix');
        $numberSuffix = config('config.reception.enquiry_number_suffix');
        $digit = config('config.reception.enquiry_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $codeNumber = (int) Enquiry::query()
            ->byTeam($teamId)
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request)
    {
        $instruction = nl2br(config('config.feature.online_registration_instruction'));

        $teams = TeamForGuestResource::collection(Team::query()
            ->get());

        $genders = Gender::getOptions();

        return compact('teams', 'instruction', 'genders');
    }

    public function getPrograms(Team $team)
    {
        $programs = ProgramForGuestResource::collection(Program::query()
            ->where('team_id', $team->id)
            ->where('config->enable_registration', true)
            ->limit(1)
            ->get());

        return compact('programs');
    }

    public function getPeriods(Team $team)
    {
        $periods = PeriodForGuestResource::collection(Period::query()
            ->where('team_id', $team->id)
            ->where('config->enable_registration', true)
            ->get());

        return compact('periods');
    }

    public function getCourses(string $period)
    {
        $period = Period::query()
            ->where('uuid', $period)
            ->firstOrFail();

        $program = request()->query('program');

        $courses = CourseForGuestResource::collection(Course::query()
            ->with('batches')
            ->whereHas('division', function ($q) use ($period, $program) {
                $q->where('period_id', $period->id)
                    ->when($program, function ($q) use ($program) {
                        $q->whereHas('program', function ($q) use ($program) {
                            $q->where('uuid', $program);
                        });
                    });
            })
            ->where('enable_registration', true)
            ->get());

        return compact('courses');
    }

    public function getBatches(string $period, string $course)
    {
        $period = Period::query()
            ->where('uuid', $period)
            ->firstOrFail();

        $course = Course::query()
            ->where('uuid', $course)
            ->firstOrFail();

        $batches = Batch::query()
            ->where('course_id', $course->id)
            ->get()
            ->map(function ($batch) {
                return [
                    'uuid' => $batch->uuid,
                    'name' => $batch->name,
                ];
            });

        return compact('batches');
    }

    public function create(Request $request)
    {
        \DB::beginTransaction();

        $codeNumberDetail = $this->codeNumber($request->team_id);

        $contact = (new CreateContact)->execute($request->all());

        $enquiry = Enquiry::forceCreate([
            'period_id' => $request->period_id,
            'date' => today()->toDateString(),
            'contact_id' => $contact->id,
            'course_id' => $request->course_id,
            'name' => $request->name,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'number_format' => Arr::get($codeNumberDetail, 'number_format'),
            'number' => Arr::get($codeNumberDetail, 'number'),
            'code_number' => Arr::get($codeNumberDetail, 'code_number'),
            'status' => EnquiryStatus::OPEN->value,
            'nature' => EnquiryNature::ADMISSION->value,
            'source_id' => $request->enquiry_source_id,
            'stage_id' => $request->enquiry_stage_id,
            'type_id' => $request->enquiry_type_id,
            'meta' => [
                'is_online' => true,
            ],
        ]);

        \DB::commit();
    }
}
