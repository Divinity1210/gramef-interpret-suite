<?php

namespace App\Http\Controllers;

use App\User;
use App\Agent;
use App\Skill;
use App\Language;
use App\ContactMethod;
use Illuminate\Http\Request;
use App\Mail\UserCreatedMail;
use App\Http\Requests\AgentRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        // dd("not here");exit;
        return view('agents.index', [
            'agents' => Agent::hasEnabledUser()
                ->filter($request->only('name', 'email', 'language', 'agent_type'))
                ->with([
                    'user.roles',
                    'languages',
                ])
                ->paginate(10),
            'languages' => Language::pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('agents.create', [
            'skills' => Skill::all(),
            'languages' => Language::orderBy('name')->pluck('name', 'id'),
            'contactMethods' => ContactMethod::all(),
        ]);
    }

    public function store(AgentRequest $request)
    {
        $user = new User();

        // Coerce potentially missing array inputs to arrays to avoid 500s
        $interpreterTypes = (array) $request->input('interpreter_types', []);
        $skills = (array) $request->input('skills', []);
        $contactMethods = (array) $request->input('contact_method', []);
        $languages = (array) $request->input('languages', []);

        $user
            ->fill($request->validated())
            ->fill(['enabled' => true, 'password' => Str::random(60)])
            ->save();

        $user->assignRole(array_merge($interpreterTypes, ['new-agent']));

        $agent = $user->agent()->create($request->validated());
        $agent->skills()->attach($skills);
        $agent->contactMethods()->attach($contactMethods);
        $agent->setLanguages($languages);

        // Send welcome/setup email, but don't fail the request if mail is misconfigured
        try {
            Mail::to($agent->user)->send(new UserCreatedMail(
                app('auth.password.broker')->createToken($agent->user),
                $agent->user,
                'emails.users.created',
                'Agent Account created'
            ));
        } catch (\Throwable $e) {
            Log::warning('Agent created but email failed to send: ' . $e->getMessage());
        }

        return redirect()->route('agents.index')->with('success', 'Created agent');
    }

    public function edit(Agent $agent)
    {
        return view('agents.edit', [
            'agent' => $agent,
            'skills' => Skill::all(),
            'languages' => Language::orderBy('name')->pluck('name', 'id'),
            'contactMethods' => ContactMethod::all(),
        ]);
    }

    public function update(AgentRequest $request, Agent $agent)
    {
        tap($agent->user)
            ->update($request->validated());

        // Only sync roles when interpreter_types is explicitly present in the form.
        // Without this guard, saving from a page that lacks the interpreter_types field
        // (e.g. bank account) would silently strip interpreter roles via syncRoles([]).
        if ($request->has('interpreter_types')) {
            $interpreterTypes = (array) $request->input('interpreter_types', []);
            $agent->user->syncRoles(array_merge($interpreterTypes, ['agent']));
        }

        $agent->update($request->validated());

        // Only sync pivot relations when the field is present in the form submission,
        // to prevent silent clearing when editing from partial forms.
        if ($request->has('skills')) {
            $agent->skills()->sync((array) $request->input('skills', []));
        }
        if ($request->has('contact_method')) {
            $agent->contactMethods()->sync((array) $request->input('contact_method', []));
        }
        if ($request->has('languages')) {
            $agent->setLanguages((array) $request->input('languages', []));
        }

        return back()->with('success', 'Updated agent');
    }

    public function destroy(Agent $agent)
    {
        $agent->user->delete();
        $agent->matchedJobs()->delete();
        $agent->delete();

        return redirect()->route('agents.archived.index')->with('success', 'Deleted agent');
    }
}