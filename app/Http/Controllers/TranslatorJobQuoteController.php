<?php

namespace App\Http\Controllers;

use App\Mail\QuoteMail;
use App\TranslatorJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Helpers\RoleHelper;

class TranslatorJobQuoteController extends Controller
{
    public function index(TranslatorJob $translatorJob)
    {
        return view('translator-jobs.quotes.index', [
            'translatorJob' => $translatorJob,
            'quotes' => $translatorJob->quotesVisibleToUser(auth()->user())->sortByDesc('created_at'),
        ]);
    }

    public function store(Request $request, TranslatorJob $translatorJob)
    {
        $validated = $request->validate([
            'cost_description' => ['required', 'string'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $validated['cost'] = round((float) $validated['cost'], 2);

        try {
            if (RoleHelper::hasRole(auth()->user(), 'admin')) {

                tap($translatorJob, function ($job) {
                    $job->update([ 'status' => 5 ]);
                })
                    ->adminQuotes()
                    ->create(array_merge($validated, ['status' => 0]));

                return back()->with('success', 'Quote sent');
            }

            tap($translatorJob->matchedLoggedInAgent(), function ($matched) {
                $matched->update([ 'status' => 2 ]);
            })
                ->quotes()
                ->create($validated);

            Mail::to(config('app.to.address'))->send(new QuoteMail(
                $translatorJob,
                $translatorJob->matchedLoggedInAgent()->quotes->last(),
                'emails.translator-jobs.quote',
                'Job Quoted',
                'admin'
            ));

            return back()->with('success', 'Quote sent');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['Unable to send quote. Please try again.']);
        }
    }

    public function update(Request $request, TranslatorJob $translatorJob, $quote)
    {
        if (\App\Helpers\RoleHelper::hasRole(auth()->user(), 'admin')) {
            $validated = $request->validate([
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $validated['cost'] = round((float) $validated['cost'], 2);

            $adminQuote = $translatorJob->adminQuotes()->where('id', $quote)->firstOrFail();
            if ($adminQuote->statusId !== 0) {
                return back()->withErrors(['Only quotes with status "quote sent" can be edited.']);
            }
            $adminQuote->update($validated);
            return back()->with('success', 'Quote updated');
        }

        if (\App\Helpers\RoleHelper::hasRole(auth()->user(), 'agent')) {
            $validated = $request->validate([
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $validated['cost'] = round((float) $validated['cost'], 2);

            $matched = $translatorJob->matchedLoggedInAgent();
            if (!$matched) {
                abort(403, 'Not authorized to edit this quote.');
            }
            $agentQuote = $matched->quotes()->where('id', $quote)->firstOrFail();
            if ($agentQuote->statusId !== 0) {
                return back()->withErrors(['Only quotes with status "quote sent" can be edited.']);
            }
            $agentQuote->update($validated);
            return back()->with('success', 'Quote updated');
        }

        abort(403, 'Not authorized.');
    }
}
