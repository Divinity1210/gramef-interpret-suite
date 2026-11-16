<?php

namespace App\Http\Controllers;

use App\Company;
use App\ContactMethod;
use App\Http\Requests\ClientRequest;
use App\Mail\UserUpdatedMail;
use Illuminate\Support\Facades\Mail;

class ClientProfileController extends Controller
{
    public function edit()
    {
        return view('clients.profile.edit', [
            'client' => auth()->user()->client,
            'contactMethods' => ContactMethod::all(),
            'companies' => Company::pluck('name', 'id'),
        ]);
    }

    public function update(ClientRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill($data);
        $user->client->fill($data);

        if ((int) ($data['is_organisation'] ?? 0) === 1) {
            if ((int) ($data['invoice_details_same_as_account'] ?? 0) === 1) {
                $data['organisation_address_line_1'] = $data['client_address_line_1'] ?? null;
                $data['organisation_address_line_2'] = $data['client_address_line_2'] ?? null;
                $data['organisation_county'] = $data['client_county'] ?? null;
                $data['organisation_postcode'] = $data['client_postcode'] ?? null;
            }
            if ((int) ($data['invoice_email_same_as_account'] ?? 0) === 1) {
                $data['organisation_email'] = $data['email'] ?? null;
            }
            if ($user->client->organisation) {
                $user->client->organisation->fill($data);
            } else {
                $user->client->organisation()->create($data);
            }
        } else {
            if ($user->client->organisation) {
                $user->client->organisation()->delete();
            }
        }

        $contactMethodsWereChanged = $request->contact_method != $user->client->contactMethods->pluck('id')->all();

        if (!$contactMethodsWereChanged && !$user->isDirty() && !$user->client->isDirty() && !optional($user->client->organisation)->isDirty()) {
            return redirect()->back()->with('success', 'Updated profile');
        }

        Mail::to(config('app.to.address'))->send(new UserUpdatedMail(
            $user,
            'emails.clients.updated',
            'A client has updated their profile'
        ));

        $user->save();
        $user->client->save();
        if ($user->client->organisation && $user->client->organisation->isDirty()) {
            $user->client->organisation->save();
        }
        $user->client->contactMethods()->sync($request->contact_method);

        return redirect()->back()->with('success', 'Updated profile');
    }
}
