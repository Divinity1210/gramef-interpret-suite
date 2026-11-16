<?php

namespace App\Http\Controllers;

use App\Client;
use App\Company;
use App\ContactMethod;
use App\Http\Requests\ClientRequest;
use App\Jobs\MatchAgents;

class NewRequestClientController extends Controller
{
    public function edit(Client $client)
    {
        return view('clients.new-requests.client.edit', [
            'client' => $client,
            'contactMethods' => ContactMethod::all(),
            'companies' => Company::pluck('name', 'id'),
        ]);
    }

    public function update(ClientRequest $request, Client $client)
    {
        $data = $request->validated();

        $client->user
            ->fill($data)
            ->fill(['enabled' => true])
            ->save();

        $client->user->syncRoles(['client']);

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
            if ($client->organisation) {
                $client->organisation->update($data);
            } else {
                $client->organisation()->create($data);
            }
        } else {
            $client->organisation()->delete();
        }

        $client->update($data);
        $client->contactMethods()->sync($request->contact_method);

        MatchAgents::dispatch($client->getRequestJob());

        return redirect()->route('clients.new-requests.index')->with('success', 'Client approved');
    }
}
