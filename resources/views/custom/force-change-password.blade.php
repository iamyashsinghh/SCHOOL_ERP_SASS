<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>

<body>
    <div>
        <form action="/force-change-password" method="post">
            @csrf

            <div>
                <label for="type">Select User Type:</label>
                <select name="type" id="type">
                    <option value="student">Student</option>
                    <option value="employee">Employee</option>
                </select>
            </div>

            <div>
                <label for="password_type">Password:</label>
                <select name="password_type" id="password_type">
                    <option value="birth_date">Date of Birth
                    </option>
                    <option value="contact_number">Contact
                        Number</option>
                    <option value="random">Random</option>
                </select>
            </div>

            <div>
                <label for="skip">Skip:</label>
                <input type="number" name="skip" id="skip" value="" min="0">
            </div>

            <div>
                <label for="limit">Limit:</label>
                <input type="number" name="limit" id="limit" value="" min="1" max="500">
            </div>

            <div>
                <button type="submit">Generate</button>
            </div>
        </form>
    </div>

    @isset($error)
        <div style="color:red; margin-top:20px;">
            {{ $error }}
        </div>
    @endisset

    @if (count($rows) > 0)
        <table style="margin-top:20px;" border="1" width="100%">
            <thead>
                <tr>
                    <th>Name</th>
                    @if (request()->type == 'student')
                        <th>Admission Number</th>
                        <th>Class</th>
                    @elseif(request()->type == 'employee')
                        <th>Employee Code</th>
                        <th>Designation</th>
                    @endif
                    <th>Username</th>
                    <th>Password</th>
                    <th>Contact Number</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ Arr::get($row, 'name') }}</td>
                        @if (request()->type == 'student')
                            <td>{{ Arr::get($row, 'code_number') }}</td>
                            <td>{{ Arr::get($row, 'course_name') . ' ' . Arr::get($row, 'batch_name') }}</td>
                        @elseif(request()->type == 'employee')
                            <td>{{ Arr::get($row, 'code_number') }}</td>
                            <td>{{ Arr::get($row, 'designation') }}</td>
                        @endif
                        <td>{{ Arr::get($row, 'username') }}</td>
                        <td>{{ Arr::get($row, 'password') }}</td>
                        <td>{{ Arr::get($row, 'contact_number') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
