<?php

namespace App\Http\Controllers;

use App\Agent;
use App\Document;
use App\SecurityType;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\AgentDocumentRequest;
use App\Mail\AgentMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\RoleHelper;

class AgentDocumentController extends Controller
{
    public function edit(Agent $agent = null)
    {
        $returnRoute = 'agents.documents.edit';

        if (!$agent) {
            $agent = auth()->user()->agent;
            $returnRoute = 'agents.profile.documents.edit';
        }

        return view($returnRoute, [
            'agent' => $agent,
            'documents' => Document::select('name', 'url')->get(),
            'securityTypes' => SecurityType::query()->where('deleted', false)->pluck('name', 'id'),
        ]);
    }

    public function update(AgentDocumentRequest $request, ?Agent $agent)
    {
        if (!RoleHelper::hasRole($request->user(), 'admin')) {
            $agent = $request->user()->agent;
        }

        $updatedDocuments = collect();

        if ($request->files->get('documents')) {
            foreach ($request->files->get('documents') as $key => $document) {
                $existingDocument = $agent->documents->firstWhere('type', config('enums.document_types')[$key]);

                $disk = env('AGENT_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
                if ($existingDocument !== null) {
                    Storage::disk($disk)->delete($existingDocument->url);
                }

                $file = $request->file("documents.$key");

                // Generate a safe filename. Some browsers upload PDFs as application/octet-stream.
                $filename = $file->hashName();
                if ($file->getMimeType() === 'application/octet-stream') {
                    $filename = method_exists($file, 'hashNameWithExtension')
                        ? $file->hashNameWithExtension('pdf')
                        : (pathinfo($filename, PATHINFO_FILENAME) . '.pdf');
                }

                $agent->documents()->updateOrCreate(
                    [
                        'type' => config('enums.document_types')[$key],
                    ],
                    [
                        'name' => $document->getClientOriginalName(),
                        'url' => $file->storeAs('agents', $filename, $disk),
                    ]
                );
                $updatedDocuments->push($key);
            }
        }

        // Use the Request instance instead of the deprecated Input facade
        if ($request->has('approve')) {
            if (RoleHelper::hasRole($agent->user, 'agent')) {
                tap($agent->user)
                ->update($request->validated());

                return back()->with('success', 'Updated agent');
            } else {
                tap($agent->user)
                    ->update($request->validated())
                    ->removeRole('new-agent')
                    ->assignRole('agent');

                $agent->update($request->validated());

                // Send approval email safely; do not let mail errors break the request
                try {
                    Mail::send('emails.agents.approved', [], function ($m) use ($agent) {
                        $m->from(config('mail.from.address'));
                        $m->to($agent->user->email)->subject('Your account has been approved');
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

        $agent->fill($request->validated());

        if (!$agent->isDirty() && !$request->files->has('documents')) {
            return back()->with('success', 'Updated documents');
        }

        if (RoleHelper::hasRole($request->user(), 'new-agent')) {
            $agent->save();

            // Notify admin of the update without failing the request if mail is misconfigured
            try {
                $to = config('app.to.address');
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($to)->send(new AgentMail(
                        $request->user(),
                        'emails.agents.updated',
                        $request->user()->getFullName() . ' has updated their profile',
                        $updatedDocuments
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send agent document update notification (new-agent)', [
                    'agent_id' => $agent->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            return back()->with('success', 'Updated documents');
        }

        if (RoleHelper::hasRole($request->user(), 'agent')) {

            // Notify admin of the update safely
            try {
                $to = config('app.to.address');
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($to)->send(new AgentMail(
                        $request->user(),
                        'emails.agents.updated',
                        $request->user()->getFullName() . ' has updated their profile',
                        $updatedDocuments
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send agent document update notification (agent)', [
                    'agent_id' => $agent->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            tap($agent->user)
                ->removeRole('agent')
                ->assignRole('new-agent');

            $agent->save();

            return back()->with('success', 'Updated documents');
        }

        $agent->save();

        if (RoleHelper::hasRole($agent->user, 'new-agent')) {


            return back()->with('success', 'Updated Documents');
        }

        return back()->with('success', 'Updated documents');
    }
}
