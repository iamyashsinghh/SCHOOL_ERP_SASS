<?php

namespace App\Imports\Academic;

use App\Concerns\ItemImport;
use App\Enums\Academic\BookListType;
use App\Models\Tenant\Academic\BookList;
use App\Models\Tenant\Academic\Course;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Academic\Subject;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BookListImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('book_list');

        $errors = $this->validate($rows);

        $this->checkForErrors('book_list', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        $courses = Course::query()
            ->byPeriod()
            ->get();

        $subjects = Subject::query()
            ->byPeriod()
            ->get();

        foreach ($rows as $index => $row) {
            $course = Arr::get($row, 'course');
            $subject = Arr::get($row, 'subject');
            $type = Str::lower(Arr::get($row, 'type'));

            $courseId = $courses->firstWhere('name', $course)?->id;

            $subjectId = $subject ? $subjects->firstWhere('name', $subject)?->id : null;

            BookList::forceCreate([
                'type' => $type,
                'course_id' => $courseId,
                'subject_id' => $subjectId,
                'title' => Arr::get($row, 'title'),
                'author' => in_array($type, [BookListType::TEXTBOOK->value, BookListType::REFERENCE_BOOK->value]) ? Arr::get($row, 'author') : null,
                'publisher' => in_array($type, [BookListType::TEXTBOOK->value, BookListType::REFERENCE_BOOK->value]) ? Arr::get($row, 'publisher') : null,
                'quantity' => $type === BookListType::NOTEBOOK->value ? (int) Arr::get($row, 'quantity', 0) : null,
                'pages' => $type === BookListType::NOTEBOOK->value ? (int) Arr::get($row, 'pages', 0) : null,
                'description' => Arr::get($row, 'description'),
                'meta' => [
                    'import_batch' => $importBatchUuid,
                    'is_imported' => true,
                ],
            ]);
        }

        $period = Period::query()
            ->whereId(auth()->user()->current_period_id)
            ->first();

        $meta = $period->meta ?? [];
        $imports['book_list'] = Arr::get($meta, 'imports.book_list', []);
        $imports['book_list'][] = [
            'uuid' => $importBatchUuid,
            'total' => count($rows),
            'created_at' => now()->toDateTimeString(),
        ];

        $meta['imports'] = $imports;
        $period->meta = $meta;
        $period->save();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $courses = Course::query()
            ->byPeriod()
            ->get()
            ->pluck('name')
            ->all();

        $subjects = Subject::query()
            ->byPeriod()
            ->get()
            ->pluck('name')
            ->all();

        $existingBooks = BookList::query()
            ->select('book_lists.*', 'courses.name as course_name')
            ->join('courses', 'book_lists.course_id', '=', 'courses.id')
            ->byPeriod()
            ->get();

        $errors = [];

        $newTitles = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $type = Arr::get($row, 'type');
            $title = Arr::get($row, 'title');
            $course = Arr::get($row, 'course');
            $subject = Arr::get($row, 'subject');
            $author = Arr::get($row, 'author');
            $publisher = Arr::get($row, 'publisher');
            $quantity = Arr::get($row, 'quantity', 0);
            $pages = Arr::get($row, 'pages', 0);
            $description = Arr::get($row, 'description');

            $currentBookTitle = $title.'-'.$course.'-'.Str::lower($type);

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.type'), 'required');
            } elseif (! in_array(strtolower($type), BookListType::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.type'), 'invalid');
            }

            if (! $title) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.title'), 'required');
            } elseif (strlen($title) < 2 || strlen($title) > 100) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.title'), 'min_max', ['min' => 2, 'max' => 100]);
            } elseif ($existingBooks->filter(function ($existingBook) use ($title, $course, $type) {
                return Str::lower($existingBook->title) == Str::lower($title) && $course == $existingBook->course_name && Str::lower($type) == $existingBook->type->value;
            })->isNotEmpty()
            ) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.title'), 'exists');
            } elseif (in_array($currentBookTitle, $newTitles)) {
                $errors[] = $this->setError($rowNo, trans('academic.book_list.props.title'), 'duplicate');
            }

            if (! in_array($course, $courses)) {
                $errors[] = $this->setError($rowNo, trans('academic.course.course'), 'invalid');
            }

            if ($subject) {
                if (! in_array($subject, $subjects)) {
                    $errors[] = $this->setError($rowNo, trans('academic.subject.subject'), 'invalid');
                }
            }

            if (in_array(Str::lower($type), [BookListType::TEXTBOOK->value, BookListType::REFERENCE_BOOK->value])) {
                if ($author && (strlen($author) < 2 || strlen($author) > 100)) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.author'), 'min_max', ['min' => 2, 'max' => 100]);
                }

                if ($publisher && (strlen($publisher) < 2 || strlen($publisher) > 100)) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.publisher'), 'min_max', ['min' => 2, 'max' => 100]);
                }
            }

            if (in_array(Str::lower($type), [BookListType::NOTEBOOK->value])) {
                if (! $quantity) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.quantity'), 'required');
                } elseif (! is_numeric($quantity)) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.quantity'), 'numeric');
                } elseif ($quantity < 0) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.quantity'), 'min', ['min' => 1]);
                } elseif ($quantity > 100) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.quantity'), 'max', ['max' => 100]);
                }

                if (! $pages) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.pages'), 'required');
                } elseif (! is_numeric($pages)) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.pages'), 'numeric');
                } elseif ($pages < 0) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.pages'), 'min', ['min' => 1]);
                } elseif ($pages > 10000) {
                    $errors[] = $this->setError($rowNo, trans('academic.book_list.props.pages'), 'max', ['max' => 10000]);
                }
            }

            if ($description && (strlen($description) < 2 || strlen($description) > 1000)) {
                $errors[] = $this->setError($rowNo, trans('academic.course.props.description'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            $newTitles[] = $title.'-'.$course.'-'.Str::lower($type);
        }

        return $errors;
    }
}
