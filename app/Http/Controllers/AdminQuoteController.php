<?php

namespace App\Http\Controllers;

use App\AdminQuote;
use App\Mail\AcceptedQuoteMail;
use Illuminate\Support\Facades\Mail;
use App\Services\NotificationHelper;
use App\Notifications\QuoteStatusNotification;

class AdminQuoteController extends Controller
{
    public function update(AdminQuote $adminQuote)
    {
        $template = 'emails.translator-jobs.accepted-quote';
        if ($adminQuote->job_type == 'App\InterpreterJob') {
            $template = 'emails.interpreter-jobs.accepted-quote';
        }
        $adminQuote->update(['status' => 1]);

        Mail::to(config('app.to.address'))->send(new AcceptedQuoteMail(
            $adminQuote,
            $template,
            'Job Quote',
            'admin'
        ));

        // Notify agent: quote accepted
        $agent = optional(optional($adminQuote->matchedAgent)->agent);
        if ($agent && $agent->user) {
            NotificationHelper::send($agent->user, new QuoteStatusNotification($adminQuote->job, 'accepted'));
        }

        return redirect()->back()->with('success', 'Quote accepted');
    }

    public function destroy(AdminQuote $adminQuote)
    {
        $adminQuote->update(['status' => 2]);
        $adminQuote->job->update(['status' => 0]);

        optional($adminQuote->job->assignedMatched()->first())->update(['status' => 1]);

        // Notify agent: quote rejected
        $agent = optional(optional($adminQuote->matchedAgent)->agent);
        if ($agent && $agent->user) {
            NotificationHelper::send($agent->user, new QuoteStatusNotification($adminQuote->job, 'rejected'));
        }

        return redirect()->back()->with('success', 'Quote rejected');
    }
}
