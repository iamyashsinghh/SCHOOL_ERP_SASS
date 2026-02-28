<?php

namespace App\Services\Exam;

use App\Concerns\HasStorage;
use App\Models\Tenant\Exam\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ExamActionService
{
    use HasStorage;

    public function storeConfig(Request $request, Exam $exam): void
    {
        $request->validate([
            'exam_form_fee' => 'sometimes|required|numeric|min:0',
            'exam_form_late_fee' => 'sometimes|required|numeric|min:0',
            'exam_form_last_date' => 'sometimes|nullable|date_format:Y-m-d',
            'group_exams' => 'sometimes|nullable|string|max:100',
            'filter_top_x_marks' => 'sometimes|nullable|numeric|min:1',
            'show_sno' => 'sometimes|boolean',
            'show_print_date_time' => 'sometimes|boolean',
            'show_watermark' => 'sometimes|boolean',
            'info' => 'nullable|string|max:1000',
            'signatory1' => 'nullable|max:50',
            'signatory1_name' => 'nullable|max:50',
            'signatory2' => 'nullable|max:50',
            'signatory2_name' => 'nullable|max:50',
            'signatory3' => 'nullable|max:50',
            'signatory3_name' => 'nullable|max:50',
            'signatory4' => 'nullable|max:50',
            'signatory4_name' => 'nullable|max:50',
            'first_attempt.title' => 'sometimes|nullable|string|max:100',
            'first_attempt.sub_title' => 'sometimes|nullable|string|max:100',
            'first_attempt.publish_marksheet' => 'sometimes|boolean',
            'second_attempt.title' => 'sometimes|nullable|string|max:100',
            'second_attempt.sub_title' => 'sometimes|nullable|string|max:100',
            'second_attempt.publish_marksheet' => 'sometimes|boolean',
            'third_attempt.title' => 'sometimes|nullable|string|max:100',
            'third_attempt.sub_title' => 'sometimes|nullable|string|max:100',
            'third_attempt.publish_marksheet' => 'sometimes|boolean',
            'fourth_attempt.title' => 'sometimes|nullable|string|max:100',
            'fourth_attempt.sub_title' => 'sometimes|nullable|string|max:100',
            'fourth_attempt.publish_marksheet' => 'sometimes|boolean',
            'fifth_attempt.title' => 'sometimes|nullable|string|max:100',
            'fifth_attempt.sub_title' => 'sometimes|nullable|string|max:100',
            'fifth_attempt.publish_marksheet' => 'sometimes|boolean',
        ]);

        if ($request->exam_form_fee > 0 || $request->exam_form_late_fee > 0) {
            $request->validate([
                'exam_form_last_date' => 'required',
            ]);
        }

        $filterTopXMarks = $request->filter_top_x_marks ?? 1;

        if ($request->group_exams) {
            $groupExams = explode(',', $request->group_exams);

            if (in_array($exam->code, $groupExams)) {
                throw ValidationException::withMessages(['message' => trans('exam.could_not_group_itself')]);
            }

            $exams = Exam::query()
                ->whereIn('code', $groupExams)
                ->byPeriod()
                ->get();

            if ($exams->count() != count($groupExams)) {
                throw ValidationException::withMessages(['message' => trans('exam.could_not_find_all_exams')]);
            }

            if ($filterTopXMarks > (count($groupExams) + 1)) {
                throw ValidationException::withMessages(['message' => trans('exam.filter_top_x_marks_max_limit', ['count' => count($groupExams) + 1])]);
            }
        }

        $config = $exam->config;
        $config['exam_form_fee'] = $request->exam_form_fee;
        $config['exam_form_late_fee'] = $request->exam_form_late_fee;
        $config['exam_form_last_date'] = $request->exam_form_last_date;
        $config['group_exams'] = $request->group_exams;
        $config['filter_top_x_marks'] = $filterTopXMarks;
        $config['title'] = $request->title;
        $config['show_sno'] = $request->boolean('show_sno');
        $config['show_print_date_time'] = $request->boolean('show_print_date_time');
        $config['show_watermark'] = $request->boolean('show_watermark');
        $config['info'] = $request->info;
        $config['signatory1'] = $request->signatory1;
        $config['signatory1_name'] = $request->signatory1_name;
        $config['signatory2'] = $request->signatory2;
        $config['signatory2_name'] = $request->signatory2_name;
        $config['signatory3'] = $request->signatory3;
        $config['signatory3_name'] = $request->signatory3_name;
        $config['signatory4'] = $request->signatory4;
        $config['signatory4_name'] = $request->signatory4_name;
        $config['first_attempt'] = [
            'title' => $request->input('first_attempt.title'),
            'sub_title' => $request->input('first_attempt.sub_title'),
            'publish_marksheet' => $request->boolean('first_attempt.publish_marksheet'),
        ];
        $config['second_attempt'] = [
            'title' => $request->input('second_attempt.title'),
            'sub_title' => $request->input('second_attempt.sub_title'),
            'publish_marksheet' => $request->boolean('second_attempt.publish_marksheet'),
        ];
        $config['third_attempt'] = [
            'title' => $request->input('third_attempt.title'),
            'sub_title' => $request->input('third_attempt.sub_title'),
            'publish_marksheet' => $request->boolean('third_attempt.publish_marksheet'),
        ];
        $config['fourth_attempt'] = [
            'title' => $request->input('fourth_attempt.title'),
            'sub_title' => $request->input('fourth_attempt.sub_title'),
            'publish_marksheet' => $request->boolean('fourth_attempt.publish_marksheet'),
        ];
        $config['fifth_attempt'] = [
            'title' => $request->input('fifth_attempt.title'),
            'sub_title' => $request->input('fifth_attempt.sub_title'),
            'publish_marksheet' => $request->boolean('fifth_attempt.publish_marksheet'),
        ];

        $exam->config = $config;
        $exam->save();
    }

    public function reorder(Request $request): void
    {
        $exams = $request->exams ?? [];

        $allExams = Exam::query()
            ->byPeriod()
            ->get();

        foreach ($exams as $index => $examItem) {
            $exam = $allExams->firstWhere('uuid', Arr::get($examItem, 'uuid'));

            if (! $exam) {
                continue;
            }

            $exam->position = $index + 1;
            $exam->save();
        }
    }

    public function uploadSignature(Request $request, Exam $exam, string $type)
    {
        request()->validate([
            'image' => 'required|image',
        ]);

        $config = $exam->config;
        $signatures = Arr::get($config, 'signatures', []);
        $signature = Arr::get($signatures, $type);

        $this->deleteImageFile(
            visibility: 'public',
            path: $signature,
        );

        $image = $this->uploadImageFile(
            visibility: 'public',
            path: 'exam/signatures/'.$type,
            input: 'image',
            maxWidth: 200,
            url: false
        );

        $signatures[$type] = $image;
        $config['signatures'] = $signatures;
        $exam->config = $config;
        $exam->save();
    }

    public function removeSignature(Request $request, Exam $exam, string $type)
    {
        $config = $exam->config;
        $signatures = Arr::get($config, 'signatures', []);
        $signature = Arr::get($signatures, $type);

        $this->deleteImageFile(
            visibility: 'public',
            path: $signature,
        );

        $signatures[$type] = null;
        $config['signatures'] = $signatures;
        $exam->config = $config;
        $exam->save();
    }
}
