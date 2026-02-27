<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Http\Request;

class BulkStudentLoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $students = Student::query()
            ->select('id', 'contact_id', 'period_id')
            ->withWhereHas('contact', function ($q) {
                $q->whereNull('user_id');
            })
            ->byPeriod()
            ->whereNull('end_date')
            ->take(100)
            ->get();

        $existingUsers = User::query()
            ->get();

        $existingUsernames = $existingUsers->pluck('username')->toArray();

        foreach ($students as $student) {
            $contact = $student->contact;

            if ($contact->user_id) {
                continue;
            }

            $name = $contact->first_name.' '.$contact->last_name;
            $name = preg_replace('/\s+/', ' ', trim($name));
            $name = str_replace(' ', '.', $name);
            $username = preg_replace('/[^a-z0-9.]/i', '', $name);
            $username = strtolower($username);

            if (in_array($username, $existingUsernames)) {
                $username .= rand(100, 999);
            }

            $email = $username.'@trw.com';

            $user = User::forceCreate([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => bcrypt($contact->contact_number),
                'email_verified_at' => now()->toDateString(),
                'status' => 'activated',
            ]);

            $preference = $user->preference;
            $preference['academic']['period_id'] = $student->period_id;
            $user->preference = $preference;
            $user->setMeta(['current_team_id' => $contact->team_id]);
            $user->save();

            $user->assignRole('student');

            $contact->user_id = $user->id;
            $contact->save();

            $existingUsernames[] = $username;
        }
    }
}
