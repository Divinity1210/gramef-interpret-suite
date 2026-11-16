<?php

namespace App\Http\Controllers;

use App\Agent;
use App\Language;
use Illuminate\Http\Request;
use App\Http\Requests\AgentRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NewAgentController extends Controller
{
    public function index(Request $request)
    {
        return view('agents.new.index', [
            'agents' => Agent::new()
                ->hasEnabledUser()
                ->filter($request->only('name', 'language', 'agent_type'))
                ->with([
                    'user',
                    'user.roles',
                    'languages',
                ])
                ->paginate(10),
            'languages' => Language::pluck('name', 'id'),
        ]);
    }

    public function update(AgentRequest $request, Agent $agent)
    {
        $interpreterTypes = (array) $request->input('interpreter_types', []);
        $skills = (array) $request->input('skills', []);
        $contactMethods = (array) $request->input('contact_method', []);
        $languages = (array) $request->input('languages', []);

        tap($agent->user)
            ->update($request->validated())
            ->syncRoles(array_merge($interpreterTypes, ['agent']));

        $agent->update($request->validated());
        $agent->skills()->sync($skills);
        $agent->contactMethods()->sync($contactMethods);
        $agent->setLanguages($languages);

        try {
            Mail::send('emails.agents.approved', [], function ($m) use ($agent) {
                $m->from(config('mail.from.address'));
                $m->to($agent->user->email)->subject('Agent account approved');
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send agent approval email', [
                'agent_id' => $agent->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Agent approved');
    }
}