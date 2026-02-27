<?php

namespace App\Imports\Student;

use App\Concerns\ItemImport;
use App\Enums\OptionType;
use App\Helpers\CalHelper;
use App\Models\Document;
use App\Models\Option;
use App\Models\Student\Student;
use App\Rules\SafeRegex;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DocumentImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('student_document');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('student_document', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $students = Student::query()
            ->byPeriod()
            ->whereIn('id', $rows->pluck('student_id'))
            ->get();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $title = Arr::get($row, 'title');
            $number = Arr::get($row, 'number');
            $issueDate = Arr::get($row, 'issue_date');
            $startDate = Arr::get($row, 'start_date');
            $expiryDate = Arr::get($row, 'expiry_date');

            $student = $students->firstWhere('id', Arr::get($row, 'student_id'));

            if ($issueDate) {
                if (is_int($issueDate)) {
                    $issueDate = Date::excelToDateTimeObject($issueDate)->format('Y-m-d');
                } else {
                    $issueDate = Carbon::parse($issueDate)->toDateString();
                }
            }

            if ($startDate) {
                if (is_int($startDate)) {
                    $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
                } else {
                    $startDate = Carbon::parse($startDate)->toDateString();
                }
            }

            if ($expiryDate) {
                if (is_int($expiryDate)) {
                    $expiryDate = Date::excelToDateTimeObject($expiryDate)->format('Y-m-d');
                } else {
                    $expiryDate = Carbon::parse($expiryDate)->toDateString();
                }
            }

            $data = [
                'documentable_type' => 'Contact',
                'documentable_id' => $student->contact_id,
                'type_id' => Arr::get($row, 'type_id'),
            ];

            if (Arr::get($row, 'has_number')) {
                $data['number'] = $number;
            } else {
                $data['title'] = $title;
            }

            $document = Document::firstOrCreate($data);
            $document->title = $title;
            $document->number = Arr::get($row, 'has_number') ? $number : null;
            $document->issue_date = $issueDate;
            $document->start_date = $startDate;
            $document->end_date = Arr::get($row, 'has_expiry_date') ? $expiryDate : null;
            $document->setMeta([
                'media_token' => (string) Str::uuid(),
            ]);
            $document->save();
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $errors = [];

        $documentTypes = Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
            ->get();

        $students = Student::query()
            ->byPeriod()
            ->summary()
            ->get();

        $newRows = [];
        $newRecords = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'student');
            $type = Arr::get($row, 'type');
            $title = Arr::get($row, 'title');
            $number = Arr::get($row, 'number');
            $issueDate = Arr::get($row, 'issue_date');
            $startDate = Arr::get($row, 'start_date');
            $expiryDate = Arr::get($row, 'expiry_date');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'required');
            } elseif (! $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'invalid');
            }

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('student.document_type.document_type'), 'required');
            }

            $documentType = null;
            if ($type) {
                $documentType = $documentTypes->filter(function ($item) use ($type) {
                    return strtolower($item->name) == strtolower($type);
                })->first();

                if (! $documentType) {
                    $errors[] = $this->setError($rowNo, trans('student.document_type.document_type'), 'invalid');
                }
            }

            if ($title && strlen($title) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.document.props.title'), 'max', ['max' => 100]);
            }

            if ($documentType->getMeta('has_number')) {
                $validPattern = $documentType->getMeta('number_format');

                if (! $number) {
                    $errors[] = $this->setError($rowNo, trans('student.document.props.number'), 'required');
                } elseif ($validPattern && SafeRegex::isValidRegex(SafeRegex::prepare($validPattern))) {
                    $pattern = SafeRegex::prepare($validPattern);
                    if (! preg_match($pattern, $number)) {
                        $errors[] = $this->setError($rowNo, trans('student.document.props.number'), 'invalid');
                    }
                }
            }

            if ($issueDate && is_int($issueDate)) {
                $issueDate = Date::excelToDateTimeObject($issueDate)->format('Y-m-d');
            }

            if ($issueDate && ! CalHelper::validateDate($issueDate)) {
                $errors[] = $this->setError($rowNo, trans('student.document.props.issue_date'), 'invalid');
            }

            if ($startDate && is_int($startDate)) {
                $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            }

            if ($startDate && ! CalHelper::validateDate($startDate)) {
                $errors[] = $this->setError($rowNo, trans('student.document.props.start_date'), 'invalid');
            }

            if ($documentType->getMeta('has_expiry_date')) {
                if (! $expiryDate) {
                    $errors[] = $this->setError($rowNo, trans('student.document.props.end_date'), 'required');
                }

                if ($expiryDate && is_int($expiryDate)) {
                    $expiryDate = Date::excelToDateTimeObject($expiryDate)->format('Y-m-d');
                }

                if ($expiryDate && ! CalHelper::validateDate($expiryDate)) {
                    $errors[] = $this->setError($rowNo, trans('student.document.props.end_date'), 'invalid');
                }
            }

            $student = $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            $row['student_id'] = $student?->id;
            $row['contact_id'] = $student?->contact_id;
            $row['type_id'] = $documentType?->id;
            $row['has_number'] = (bool) $documentType?->getMeta('has_number');
            $row['has_expiry_date'] = (bool) $documentType?->getMeta('has_expiry_date');
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
