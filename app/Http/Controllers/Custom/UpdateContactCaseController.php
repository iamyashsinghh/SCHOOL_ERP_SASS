<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UpdateContactCaseController extends Controller
{
    public function __invoke(Request $request)
    {
        $field = $request->query('field', 'name'); // 'name' or 'address'
        $case = $request->query('case', 'title'); // 'title' or 'upper'

        if (! in_array($field, ['name', 'address'])) {
            return 'Invalid field. Must be "name" or "address"';
        }

        if (! in_array($case, ['title', 'upper', 'lower'])) {
            return 'Invalid case. Must be "title" or "upper" or "lower"';
        }

        Contact::chunk(100, function ($contacts) use ($field, $case) {
            foreach ($contacts as $contact) {
                if ($field === 'name') {
                    $this->updateNameCase($contact, $case);
                } else {
                    $this->updateAddressCase($contact, $case);
                }

                $contact->save();
            }
        });

        return "Successfully updated {$field} fields to {$case} case";
    }

    private function updateNameCase(Contact $contact, string $case): void
    {
        $nameFields = ['first_name', 'middle_name', 'third_name', 'last_name'];

        foreach ($nameFields as $field) {
            if ($contact->$field) {
                $contact->$field = $this->applyCase($contact->$field, $case);
            }
        }
    }

    private function updateAddressCase(Contact $contact, string $case): void
    {
        $address = $contact->address ?? [];

        $presentFields = ['address_line1', 'address_line2', 'city', 'state', 'country'];
        foreach ($presentFields as $field) {
            if (Arr::get($address, "present.{$field}")) {
                $address['present'][$field] = $this->applyCase(Arr::get($address, "present.{$field}"), $case);
            }
        }

        $permanentFields = ['address_line1', 'address_line2', 'city', 'state', 'country'];
        foreach ($permanentFields as $field) {
            if (Arr::get($address, "permanent.{$field}")) {
                $address['permanent'][$field] = $this->applyCase(Arr::get($address, "permanent.{$field}"), $case);
            }
        }

        if (isset($address['present']['zipcode'])) {
            $address['present']['zipcode'] = Arr::get($contact->address, 'present.zipcode');
        }

        if (isset($address['permanent']['zipcode'])) {
            $address['permanent']['zipcode'] = Arr::get($contact->address, 'permanent.zipcode');
        }

        if (isset($address['permanent']['same_as_present_address'])) {
            $address['permanent']['same_as_present_address'] = (bool) Arr::get($contact->address, 'permanent.same_as_present_address');
        }

        $contact->address = $address;
    }

    private function applyCase(string $value, string $case): string
    {
        return match ($case) {
            'upper' => Str::upper($value),
            'lower' => Str::lower($value),
            default => Str::title($value),
        };
    }
}
