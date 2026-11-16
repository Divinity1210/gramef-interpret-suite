<?php

namespace App\Http\Controllers;

use App\User;
use App\Client;
use App\Company;
use App\ContactMethod;
use Illuminate\Http\Request;
use App\Mail\UserCreatedMail;
use App\Http\Requests\ClientRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::hasEnabledUser()
            ->with([
                'user',
                'organisation',
                'organisation.company',
            ])
            ->withCount([
                'interpreterJobs as active_interpreter_jobs_count' => function ($q) {
                    $q->active();
                },
                'translatorJobs as active_translator_jobs_count' => function ($q) {
                    $q->active();
                },
            ])
            ->filter($request->only(['company','search']))
            ->paginate(10);

        return view('clients.index', [
           'clients' => $clients,
           'companies' => Company::pluck('name','id')
        ]);
    }

    public function create()
    {
        return view('clients.create', [
            'contactMethods' => ContactMethod::all(),
            'companies' => Company::pluck('name', 'id'),
        ]);
    }
    public function toggleShowAgents(Client $client)
    {
        Log::info('Before toggle: show_agents = ' . $client->show_agents);
        // Toggle the show_agents value
        $client->show_agents = !$client->show_agents;
         if ($client->save()) {
        Log::info('After toggle: show_agents = ' . $client->show_agents);
    } else {
        Log::error('Failed to save client.');
    }

        // Redirect back with a success message
        return redirect()->back()->with('status', 'Agent details visibility updated!');
    }
    public function store(ClientRequest $request)
    {
        try {
            $user = new User();

            $user
                ->fill($request->validated())
                ->fill(['enabled' => true, 'password' => Str::random(60)])
                ->save();

            $user->assignRole('client');

            $user->client()
                ->create($request->validated())
                ->contactMethods()
                ->attach($request->contact_method);

            if ($request->is_organisation) {
                $user->client->organisation()->create($request->validated());
            }

            Mail::to($user)->send(new UserCreatedMail(
                app('auth.password.broker')->createToken($user),
                $user,
                'emails.users.created',
                'Client Account created'
            ));

            return redirect()->route('clients.index')->with('success', 'Client created');
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle any database errors gracefully
             Log::error('Database error during client creation: ' . $e->getMessage());
             return back()->withErrors(['error' => 'An error occurred while creating the client. Please try again.'])->withInput();
        }
    }

    public function edit(Client $client)
    {
        return view('clients.edit', [
            'client' => $client,
            'contactMethods' => ContactMethod::all(),
            'companies' => Company::pluck('name', 'id'),
        ]);
    }

    public function update(ClientRequest $request, Client $client)
    {
        $data = $request->validated();

        $client->user->update($data);

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

        return back()->with('success', 'Client updated');
    }

    public function destroy(Client $client)
    {
        $client->user->delete();
        $client->organisation()->delete();
        $client->interpreterJobs()->delete();
        $client->translatorJobs()->delete();
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted');
    }
}
