<?php

namespace App\Http\Controllers\Custom;

use App\Enums\OptionType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Employee\Employee;
use App\Models\Option;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SyncDocumentNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $documentTypes = Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::STUDENT_DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
            ->get();

        if ($request->query('type', 'student') === 'student') {
            return $this->syncStudentDocumentNumber($documentTypes);
        } else {
            return $this->syncEmployeeDocumentNumber($documentTypes);
        }
    }

    private function syncStudentDocumentNumber(Collection $documentTypes)
    {
        $count = 0;

        Student::query()
            ->select('students.contact_id', 'contacts.unique_id_number1', 'contacts.unique_id_number2', 'contacts.unique_id_number3', 'contacts.unique_id_number4', 'contacts.unique_id_number5')
            ->leftJoin('contacts', 'students.contact_id', '=', 'contacts.id')
            ->where('contacts.team_id', auth()->user()?->current_team_id)
            ->chunk(100, function ($studentChunks) use ($documentTypes, &$count) {
                foreach ($studentChunks as $student) {
                    $count += $this->syncDocument(
                        $student->unique_id_number1,
                        $student->contact_id,
                        $documentTypes,
                        config('config.student.unique_id_number1_label')
                    );

                    $count += $this->syncDocument(
                        $student->unique_id_number2,
                        $student->contact_id,
                        $documentTypes,
                        config('config.student.unique_id_number2_label')
                    );

                    $count += $this->syncDocument(
                        $student->unique_id_number3,
                        $student->contact_id,
                        $documentTypes,
                        config('config.student.unique_id_number3_label')
                    );

                    $count += $this->syncDocument(
                        $student->unique_id_number4,
                        $student->contact_id,
                        $documentTypes,
                        config('config.student.unique_id_number4_label')
                    );

                    $count += $this->syncDocument(
                        $student->unique_id_number5,
                        $student->contact_id,
                        $documentTypes,
                        config('config.student.unique_id_number5_label')
                    );
                }
            });

        return $count.' documents synced';
    }

    private function syncEmployeeDocumentNumber(Collection $documentTypes)
    {
        $count = 0;

        Employee::query()
            ->select('employees.contact_id', 'contacts.unique_id_number1', 'contacts.unique_id_number2', 'contacts.unique_id_number3', 'contacts.unique_id_number4', 'contacts.unique_id_number5')
            ->leftJoin('contacts', 'employees.contact_id', '=', 'contacts.id')
            ->where('contacts.team_id', auth()->user()?->current_team_id)
            ->chunk(100, function ($employeeChunks) use ($documentTypes, &$count) {
                foreach ($employeeChunks as $employee) {
                    $count += $this->syncDocument(
                        $employee->unique_id_number1,
                        $employee->contact_id,
                        $documentTypes,
                        config('config.employee.unique_id_number1_label')
                    );

                    $count += $this->syncDocument(
                        $employee->unique_id_number2,
                        $employee->contact_id,
                        $documentTypes,
                        config('config.employee.unique_id_number2_label')
                    );

                    $count += $this->syncDocument(
                        $employee->unique_id_number3,
                        $employee->contact_id,
                        $documentTypes,
                        config('config.employee.unique_id_number3_label')
                    );

                    $count += $this->syncDocument(
                        $employee->unique_id_number4,
                        $employee->contact_id,
                        $documentTypes,
                        config('config.employee.unique_id_number4_label')
                    );

                    $count += $this->syncDocument(
                        $employee->unique_id_number5,
                        $employee->contact_id,
                        $documentTypes,
                        config('config.employee.unique_id_number5_label')
                    );
                }
            });

        return $count.' documents synced';
    }

    private function syncDocument($number, $contactId, $documentTypes, $documentTypeLabel)
    {
        if (! $number) {
            return;
        }

        $documentType = $documentTypes->firstWhere('name', $documentTypeLabel);

        if ($documentType) {
            $document = Document::query()
                ->where('documentable_type', 'Contact')
                ->where('documentable_id', $contactId)
                ->where('type_id', $documentType->id)
                ->first();

            if ($document) {
                $document->update([
                    'title' => $document->title ?? $documentType->name,
                    'number' => $number,
                ]);
            } else {
                Document::create([
                    'documentable_type' => 'Contact',
                    'documentable_id' => $contactId,
                    'type_id' => $documentType->id,
                    'title' => $documentType->name,
                    'number' => $number,
                ]);
            }

            return 1;
        }

        return 0;
    }
}
