{{ $fatherMissing }} students have missing father guardian <br />
{{ $motherMissing }} students have missing mother guardian <br /><br />

<a href="{{ route('custom.sync-guardian', ['confirm' => 'yes']) }}">Sync Guardian</a>

<p>
    This will use the student's contact number with father/mother name to create a guardian. If the record already
    exists,
    it will be linked to the student's guardian record. This will create/update first 500 records.
</p>
