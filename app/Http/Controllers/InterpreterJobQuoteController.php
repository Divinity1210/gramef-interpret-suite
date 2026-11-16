<?php

namespace App\Http\Controllers;

use App\InterpreterJob;
use Illuminate\Http\Request;
use App\Helpers\RoleHelper;
use Illuminate\Support\Facades\Schema;

class InterpreterJobQuoteController extends Controller
{
    public function index(InterpreterJob $interpreterJob)
    {
        if ($interpreterJob->status == 3) {
            $latest = $interpreterJob->latestAdminQuote();
            if ($latest && $latest->status_id === 2) {
                $interpreterJob->update(['status' => 0]);
                optional($interpreterJob->assignedMatched()->first())->update(['status' => 1]);
            }
        }
        return view('interpreter-jobs.quotes.index', [
            'interpreterJob' => $interpreterJob,
            'quotes' => $interpreterJob->quotesVisibleToUser(auth()->user())->sortByDesc('created_at'),
        ]);
    }

    public function store(Request $request, InterpreterJob $interpreterJob)
    {
        $validated = $request->validate([
            'interpreting_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'travel_time' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'travel_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'mileage_miles' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'mileage_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'cost_description' => ['required', 'string'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $normalized = $validated;
        foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost', 'cost'] as $key) {
            $normalized[$key] = round((float) $normalized[$key], 2);
        }

        if (!RoleHelper::hasRole(auth()->user(), 'admin') && !RoleHelper::hasRole(auth()->user(), 'agent')) {
            abort(403, 'Only agents can send quotes.');
        }

        try {
            if (RoleHelper::hasRole(auth()->user(), 'admin')) {

                tap($interpreterJob, function ($job) {
                    $job->update([ 'status' => 5 ]);
                })
                    ->adminQuotes()
                    ->create((function () use ($normalized) {
                        $base = [
                            'cost_description' => $normalized['cost_description'],
                            'cost' => $normalized['cost'],
                            'status' => 0,
                        ];
                        $keys = ['interpreting_cost','travel_time','travel_cost','mileage_miles','mileage_cost'];
                        foreach ($keys as $k) {
                            if (Schema::hasColumn('admin_quotes', $k)) {
                                $base[$k] = $normalized[$k];
                            }
                        }
                        return $base;
                    })());

                return back()->with('success', 'Quote sent');
            }

            tap($interpreterJob->matchedLoggedInAgent(), function ($matched) {
                $matched->update([ 'status' => 2 ]);
            })
                ->quotes()
                ->create($normalized);

            return back()->with('success', 'Quote sent');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['Unable to send quote. Please try again.']);
        }
    }

    public function update(Request $request, InterpreterJob $interpreterJob, $quote)
    {
        if (RoleHelper::hasRole(auth()->user(), 'admin')) {
            $validated = $request->validate([
                'interpreting_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_time' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_miles' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $normalized = $validated;
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost', 'cost'] as $key) {
                $normalized[$key] = round((float) $normalized[$key], 2);
            }

            $adminQuote = $interpreterJob->adminQuotes()->where('id', $quote)->firstOrFail();
            if ($adminQuote->statusId !== 0) {
                return back()->withErrors(['Only quotes with status "quote sent" can be edited.']);
            }

            $updates = [
                'cost_description' => $normalized['cost_description'],
                'cost' => $normalized['cost'],
            ];
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost'] as $k) {
                if (Schema::hasColumn('admin_quotes', $k)) {
                    $updates[$k] = $normalized[$k];
                }
            }

            $adminQuote->update($updates);
            return back()->with('success', 'Quote updated');
        }

        if (RoleHelper::hasRole(auth()->user(), 'agent')) {
            $validated = $request->validate([
                'interpreting_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_time' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_miles' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $normalized = $validated;
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost', 'cost'] as $key) {
                $normalized[$key] = round((float) $normalized[$key], 2);
            }

            $matched = $interpreterJob->matchedLoggedInAgent();
            if (!$matched) {
                abort(403, 'Not authorized to edit this quote.');
            }
            $agentQuote = $matched->quotes()->where('id', $quote)->firstOrFail();
            if ($agentQuote->statusId !== 0) {
                return back()->withErrors(['Only quotes with status "quote sent" can be edited.']);
            }

            $agentQuote->update($normalized);
            return back()->with('success', 'Quote updated');
        }

        abort(403, 'Not authorized.');
    }
}
