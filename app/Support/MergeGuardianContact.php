<?php

namespace App\Support;

use App\Models\Tenant\Contact;
use App\Models\Tenant\Guardian;

trait MergeGuardianContact
{
    public function mergeGuardianContact($contactIds, string $type = 'collection')
    {
        $guardians = Guardian::whereIn('primary_contact_id', $contactIds)->get();

        $contactIds = array_merge($contactIds, $guardians->pluck('contact_id')->toArray());

        if ($type == 'collection') {
            return Contact::whereIn('id', $contactIds)->get();
        }

        return Contact::whereIn('id', $contactIds);
    }
}
