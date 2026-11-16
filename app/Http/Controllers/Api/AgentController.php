<?php

namespace App\Http\Controllers\Api;

use App\Agent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FindAgentRequest;
use App\InterpreterJob;
use App\TranslatorJob;
use Illuminate\Support\Facades\Response;

class AgentController extends Controller
{
    public function index(FindAgentRequest $request, string $jobType)
    {
        //	print_r(  $request->all() );
        switch ($jobType) {
            case 'interpreter':
                $job = InterpreterJob::make( $request->all() ); // Create an InterpreterJob instance
                //  dd("Created Interpreter Job Instance: ", $job);
                $baseQuery = Agent::hasEnabledUser()->matchesInterpreterJob($job);

                // When DBS is NOT required (security_type_id === 4), do NOT apply DBS/document filters.
                // Only exclude agents who have restricted job notifications.
                if ((int) $request->get('security_type_id') === 4) {
                    $baseQuery->where('restrict_job_notifications', false);
                } else {
                    // Apply normal DBS/document filtering when security is required
                    $baseQuery->canBeMatchedToInterpreterJobs($job);
                }

                return Response::json(
                    $baseQuery
                        ->searchName($request->get('term'))
                        ->with(['user' => function ($query) {
                            return $query->select('id', 'first_name', 'last_name');
                        }])
                        ->select('id', 'user_id')
                        ->paginate()
                );

            case 'translator':
                return Response::json(
                    Agent::matchesTranslatorJob(
                        TranslatorJob::make($request->all())
                    )
                        //->canBeMatchedToJobs()
                        ->canBeMatchedToTranslatorJobs()
                        ->searchName($request->get('term'))
                        ->with(['user' => function ($query) {
                            return $query->select('id', 'first_name', 'last_name');
                        }])
                        ->select('id', 'user_id')
                        ->paginate()
                );
        }
    }
}
