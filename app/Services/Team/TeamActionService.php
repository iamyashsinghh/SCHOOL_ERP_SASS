<?php

namespace App\Services\Team;

use App\Models\Tenant\Team;
use Illuminate\Http\Request;

class TeamActionService
{
    public function storeConfig(Request $request, Team $team): void
    {
        $team->setConfig([
            'name' => $request->name,
            'title1' => $request->title1,
            'title2' => $request->title2,
            'title3' => $request->title3,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'zipcode' => $request->zipcode,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'fax' => $request->fax,
            'incharge1' => [
                'title' => $request->input('incharge1.title'),
                'name' => $request->input('incharge1.name'),
                'email' => $request->input('incharge1.email'),
                'contact_number' => $request->input('incharge1.contact_number'),
            ],
            'incharge2' => [
                'title' => $request->input('incharge2.title'),
                'name' => $request->input('incharge2.name'),
                'email' => $request->input('incharge2.email'),
                'contact_number' => $request->input('incharge2.contact_number'),
            ],
            'incharge3' => [
                'title' => $request->input('incharge3.title'),
                'name' => $request->input('incharge3.name'),
                'email' => $request->input('incharge3.email'),
                'contact_number' => $request->input('incharge3.contact_number'),
            ],
            'incharge4' => [
                'title' => $request->input('incharge4.title'),
                'name' => $request->input('incharge4.name'),
                'email' => $request->input('incharge4.email'),
                'contact_number' => $request->input('incharge4.contact_number'),
            ],
            'incharge5' => [
                'title' => $request->input('incharge5.title'),
                'name' => $request->input('incharge5.name'),
                'email' => $request->input('incharge5.email'),
                'contact_number' => $request->input('incharge5.contact_number'),
            ],
        ], true);
    }
}
