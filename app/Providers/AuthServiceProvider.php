<?php

namespace App\Providers;

use App\Document;
use App\InterpreterJob;
use App\Policies\DocumentPolicy;
use App\Policies\InterpreterJobPolicy;
use App\Policies\TranslatorJobPolicy;
use App\Policies\PaymentProofPolicy;
use App\Policies\PayslipPolicy;
use App\TranslatorJob;
use App\PaymentProof;
use App\Payslip;
use App\Timesheet;
use App\Policies\TimesheetPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        InterpreterJob::class => InterpreterJobPolicy::class,
        TranslatorJob::class => TranslatorJobPolicy::class,
        Document::class => DocumentPolicy::class,
        PaymentProof::class => PaymentProofPolicy::class,
        Payslip::class => PayslipPolicy::class,
        Timesheet::class => TimesheetPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
   
        $this->registerPolicies();

        //
    }
}
