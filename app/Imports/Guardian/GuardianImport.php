<?php

namespace App\Imports\Guardian;

use App\Actions\CreateContact;
use App\Concerns\ItemImport;
use App\Enums\FamilyRelation;
use App\Enums\Gender;
use App\Helpers\CalHelper;
use App\Models\Guardian;
use App\Models\Student\Student;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class GuardianImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 100;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('guardian');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('guardian', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $studentId = Arr::get($row, 'student_id');
            $studentContactId = Arr::get($row, 'contact_id');

            $birthDate = Arr::get($row, 'date_of_birth');

            if (empty($birthDate)) {
                $birthDate = null;
            } elseif (is_int(Arr::get($row, 'date_of_birth'))) {
                $birthDate = Date::excelToDateTimeObject(Arr::get($row, 'date_of_birth'))->format('Y-m-d');
            } else {
                $birthDate = Carbon::parse(Arr::get($row, 'date_of_birth'))->toDateString();
            }

            $contact = (new CreateContact)->execute([
                'name' => Arr::get($row, 'guardian_name'),
                'contact_number' => (string) Arr::get($row, 'contact_number'),
                'email' => Arr::get($row, 'email'),
                'gender' => strtolower(Arr::get($row, 'gender')),
                'birth_date' => $birthDate,
                'source' => 'guardian',
            ]);

            $guardian = Guardian::firstOrCreate([
                'primary_contact_id' => $studentContactId,
                'contact_id' => $contact->id,
                'relation' => strtolower(Arr::get($row, 'relation')),
            ]);

            if (Arr::get($row, 'username') && Arr::get($row, 'email') && Arr::get($row, 'password') && Arr::get($row, 'email')) {
                $existingUser = User::query()
                    ->where('email', Arr::get($row, 'email'))
                    ->orWhere('username', Arr::get($row, 'username'))
                    ->first();

                if (! $existingUser) {
                    $user = User::forceCreate([
                        'name' => $contact->name,
                        'username' => Arr::get($row, 'username'),
                        'email' => Arr::get($row, 'email'),
                        'password' => bcrypt(Arr::get($row, 'password')),
                        'status' => 'activated',
                        'email_verified_at' => now()->toDateTimeString(),
                        'meta' => ['current_team_id' => auth()->user()->current_team_id],
                    ]);

                    $user->assignRole('guardian');
                } else {
                    $user = $existingUser;
                }

                $contact->user_id = $user->id;
                $contact->save();
            }
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['guardian'] = Arr::get($meta, 'imports.guardian', []);
        $imports['guardian'][] = [
            'uuid' => $importBatchUuid,
            'total' => count($rows),
            'created_at' => now()->toDateTimeString(),
        ];

        $meta['imports'] = $imports;
        $team->meta = $meta;
        $team->save();

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $relations = FamilyRelation::getKeys();
        $genders = Gender::getKeys();

        $students = Student::query()
            ->summary()
            ->get();

        $existingUserEmails = User::query()
            ->get()
            ->pluck('email')
            ->all();

        $existingUsernames = User::query()
            ->get()
            ->pluck('username')
            ->all();

        // $users = User::query()
        //     ->whereIn('id', $students->pluck('user_id'))
        //     ->get();

        $errors = [];

        $newRows = [];
        $newNames = [];
        $newUsernames = [];
        $newEmails = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'student');
            $guardianName = Arr::get($row, 'guardian_name');
            $contactNumber = Arr::get($row, 'contact_number');
            $birthDate = Arr::get($row, 'date_of_birth');
            $gender = Arr::get($row, 'gender');
            $relation = Arr::get($row, 'relation');
            $email = Arr::get($row, 'email');
            $username = Arr::get($row, 'username');
            $password = Arr::get($row, 'password');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'required');
            } elseif (! $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'invalid');
            }

            $student = $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            if (! $guardianName) {
                $errors[] = $this->setError($rowNo, trans('guardian.props.name'), 'required');
            } elseif ($guardianName && strlen($guardianName) < 2 || strlen($guardianName) > 100) {
                $errors[] = $this->setError($rowNo, trans('guardian.props.name'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if (! $contactNumber) {
                $errors[] = $this->setError($rowNo, trans('contact.props.contact_number'), 'required');
            }

            if (is_int($birthDate)) {
                $birthDate = Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
            }

            if ($birthDate && ! CalHelper::validateDate($birthDate)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.birth_date'), 'invalid');
            }

            if ($gender && ! in_array(strtolower($gender), $genders)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.gender'), 'invalid');
            }

            if (! $relation) {
                $errors[] = $this->setError($rowNo, trans('contact.props.relation'), 'required');
            } elseif (! in_array(strtolower($relation), $relations)) {
                $errors[] = $this->setError($rowNo, trans('contact.props.relation'), 'invalid');
            }

            if (! $username) {
                $errors[] = $this->setError($rowNo, trans('auth.login.props.username'), 'required');
            }

            if ($username) {
                if (in_array($username, $existingUsernames)) {
                    $errors[] = $this->setError($rowNo, trans('auth.login.props.username'), 'exists');
                } else {
                    // lets not add it to existing usernames as siblings can have same guardian
                    // array_push($existingUsernames, $username);
                }

                $validUsername = preg_match('/^(?=.{4,20}$)(?![_.])(?!.*[_.]{2})[a-zA-Z0-9._]+(?<![_.])$/', $username);
                if (! $validUsername) {
                    $errors[] = $this->setError($rowNo, trans('auth.login.props.username'), 'invalid');
                }
            }

            // Let's not check for duplicate usernames as siblings can have same guardian
            // if ($username && in_array($username, $newUsernames)) {
            //     $errors[] = $this->setError($rowNo, trans('auth.login.props.username'), 'duplicate');
            // }

            if ($password && strlen($password) < 6 || strlen($password) > 32) {
                $errors[] = $this->setError($rowNo, trans('auth.login.props.password'), 'min_max', ['min' => 6, 'max' => 32]);
            }

            $newNames[] = $name;
            $newUsernames[] = $username;
            $newEmails[] = $email;

            $row['student_id'] = $student?->id;
            $row['contact_id'] = $student?->contact_id;
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
